<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockRequestForm;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Services\AccessRightService;
use App\Services\ProductCatalogue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The requestor side of stock transfer requests. Approval is done in WFS, which v2 does not
 * talk to yet: submitting marks a request "Submitted" with WFS_connection = 0, which is exactly
 * what legacy's insertIntoWFS() retry picks up once WFS is integrated.
 */
class StockRequestController extends Controller
{
    public const PAGE_MANAGE = 'Manage Stock Request';

    public const PAGE_REQUEST = 'Stock Request';

    public const PAGE_UNSAVED = 'Unsaved Stock Request';

    private const SORTABLE = ['transaction_no', 'origin', 'date_filed', 'dept', 'date_needed', 'status'];

    public function __construct(protected AccessRightService $accessRights)
    {
    }

    /**
     * Main dashboard: every saved request of the user's department, split the legacy way.
     */
    public function dashboard(Request $request): Response
    {
        $requests = StockRequest::query()
            ->withProgress()
            ->active()
            ->where('isSaved', true)
            ->where('dept', $request->user()->dept)
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockRequest $row) => $this->row($row));

        return Inertia::render('StockRequests/Dashboard', [
            'pending' => $requests->filter(fn ($row) => strtolower((string) $row['status']) === 'pending')->values(),
            'inProgress' => $requests->filter(fn ($row) => ! $row['completed'] && strtolower((string) $row['status']) === 'fully approved')->values(),
            'completed' => $requests->filter(fn ($row) => $row['completed'])->values(),
            'total' => $requests->count(),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $this->listFilters($request);

        $requests = $this->ownRequests($request, saved: true)
            ->stillOpen()
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: true))
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn (StockRequest $row) => $this->row($row));

        return Inertia::render('StockRequests/Index', [
            'requests' => $requests,
            'filters' => $filters,
            'can' => [
                'create' => $this->can($request, self::PAGE_REQUEST, 'create'),
                'edit' => $this->can($request, self::PAGE_REQUEST, 'edit'),
                'view' => $this->can($request, self::PAGE_REQUEST, 'view'),
                'delete' => $this->can($request, self::PAGE_MANAGE, 'delete'),
            ],
        ]);
    }

    /**
     * CSV of the whole filtered list (Manage Stock Request, or the unsaved drafts with ?unsaved=1).
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->listFilters($request);
        $unsaved = $request->boolean('unsaved');

        abort_unless($this->can($request, $unsaved ? self::PAGE_UNSAVED : self::PAGE_MANAGE, 'view'), HttpResponse::HTTP_FORBIDDEN);

        $rows = $this->ownRequests($request, saved: ! $unsaved)
            ->when(! $unsaved, fn ($query) => $query->stillOpen())
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: ! $unsaved))
            ->get();

        return $this->csv($unsaved ? 'unsaved-requests.csv' : 'stock-requests.csv', $rows);
    }

    /**
     * Drafts the user started but never saved (legacy isSaved = 0).
     */
    public function unsaved(Request $request): Response
    {
        $filters = $this->listFilters($request);

        $requests = $this->ownRequests($request, saved: false)
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: false))
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn (StockRequest $row) => $this->row($row));

        return Inertia::render('StockRequests/Unsaved', [
            'requests' => $requests,
            'filters' => $filters,
            'can' => [
                'edit' => $this->can($request, self::PAGE_REQUEST, 'edit'),
                'view' => $this->can($request, self::PAGE_REQUEST, 'view'),
                'delete' => $this->can($request, self::PAGE_UNSAVED, 'delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('StockRequests/Create', [
            'origins' => StockRequestForm::originOptions($user->dept),
            'defaults' => [
                'date_filed' => now()->format('Y-m-d'),
                'time_filed' => now()->format('H:i'),
                'dept' => $user->dept,
                'requestor' => $user->name,
                'requested_by' => $this->requestedByStamp($request),
            ],
        ]);
    }

    public function store(StockRequestForm $form): RedirectResponse
    {
        $user = $form->user();

        $stockRequest = DB::transaction(function () use ($form, $user) {
            $stockRequest = StockRequest::create([
                'date_filed' => now()->toDateString(),
                'time_filed' => now()->format('H:i'),
                'date_needed' => $form->validated('date_needed'),
                'dept' => $user->dept,
                'remarks' => $form->validated('remarks'),
                'requested_by' => $this->requestedByStamp($form),
                'created_by' => $user->username,
                'status' => StockRequest::STATUS_PENDING,
                'isSaved' => true,
                'active' => true,
                'origin' => $form->validated('origin'),
                'requestor' => $form->validated('requestor'),
            ]);

            $stockRequest->update([
                'transaction_no' => StockRequest::makeTransactionNo($stockRequest->date_filed, $stockRequest->id),
            ]);

            $this->replaceItems($stockRequest, $form->items(), $user->username);

            return $stockRequest;
        });

        return redirect()->route('stockrequests.edit', $stockRequest)
            ->with('success', 'Request successfully saved. You can now submit it.');
    }

    public function show(Request $request, StockRequest $stockRequest): Response
    {
        $this->ensureVisible($request, $stockRequest);

        $stockRequest = StockRequest::query()->withProgress()->findOrFail($stockRequest->id);

        return Inertia::render('StockRequests/Show', [
            'stockRequest' => $this->detail($stockRequest),
            'items' => $stockRequest->items()->get(['id', 'stock_code', 'description', 'uom', 'requested_qty', 'remarks']),
        ]);
    }

    public function edit(Request $request, StockRequest $stockRequest): Response
    {
        $this->ensureOwnEditable($request, $stockRequest);

        return Inertia::render('StockRequests/Edit', [
            'stockRequest' => $this->detail($stockRequest),
            'items' => $stockRequest->items()->get(['id', 'stock_code', 'description', 'uom', 'requested_qty', 'remarks']),
            'origins' => collect(StockRequestForm::originOptions($request->user()->dept))
                ->push($stockRequest->origin)->filter()->unique()->values(),
        ]);
    }

    /**
     * Save changes; with `submit` set, also submit the saved request for approval.
     */
    public function update(StockRequestForm $form, StockRequest $stockRequest): RedirectResponse
    {
        $this->ensureOwnEditable($form, $stockRequest);

        $submit = $form->boolean('submit');

        DB::transaction(function () use ($form, $stockRequest, $submit) {
            $stockRequest->update([
                'date_needed' => $form->validated('date_needed'),
                'remarks' => $form->validated('remarks'),
                'updated_by' => $form->user()->username,
                'status' => $submit ? StockRequest::STATUS_SUBMITTED : StockRequest::STATUS_PENDING,
                'isSaved' => true,
                'WFS_connection' => 0,
                'origin' => $form->validated('origin'),
                'requestor' => $form->validated('requestor'),
            ]);

            $this->replaceItems($stockRequest, $form->items(), $form->user()->username);
        });

        return $submit
            ? redirect()->route('stockrequests.index')->with('success', "{$stockRequest->transaction_no} has been submitted for approval.")
            : back()->with('success', 'Request successfully saved.');
    }

    /**
     * Submit a saved, pending request straight from the Manage Stock Request list.
     */
    public function submit(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->ensureOwn($request, $stockRequest);

        if (! $stockRequest->isPending()) {
            return back()->with('error', 'This request has already been submitted.');
        }

        if (! $stockRequest->items()->exists()) {
            return back()->with('error', 'Please add at least 1 item before submitting.');
        }

        $missing = collect(['dept' => 'Department', 'date_needed' => 'Date Needed', 'requested_by' => 'Requested By', 'origin' => 'Origin'])
            ->filter(fn ($label, $field) => blank($stockRequest->{$field}));

        if ($missing->isNotEmpty()) {
            return back()->with('error', 'Open this request and complete: '.$missing->implode(', ').'.');
        }

        $stockRequest->update([
            'updated_by' => $request->user()->username,
            'status' => StockRequest::STATUS_SUBMITTED,
            'isSaved' => true,
            'WFS_connection' => 0,
        ]);

        return back()->with('success', "{$stockRequest->transaction_no} has been submitted for approval.");
    }

    public function destroy(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->ensureOwnEditable($request, $stockRequest);

        $page = $stockRequest->isSaved ? self::PAGE_MANAGE : self::PAGE_UNSAVED;
        abort_unless($this->can($request, $page, 'delete'), HttpResponse::HTTP_FORBIDDEN);

        $stockRequest->update([
            'deleted_at' => now(),
            'deleted_by' => $request->user()->username,
            'active' => false,
        ]);

        return back()->with('success', 'Request has been deleted.');
    }

    public function print(Request $request, StockRequest $stockRequest): HttpResponse
    {
        $this->ensureVisible($request, $stockRequest);

        $items = $stockRequest->items()->get();
        $products = app(ProductCatalogue::class)->findByCodes($items->pluck('stock_code')->filter()->unique()->values()->all());

        return Pdf::loadView('reports.stock-request-requestor', [
            'stockRequest' => $stockRequest,
            'requested_items' => $items,
            'products' => $products,
        ])->setPaper('legal', 'portrait')->download("StockRequest#{$stockRequest->transaction_no}.pdf");
    }

    /**
     * Catalogue search for the item picker (starts-with on stock code or item name, like legacy).
     */
    public function products(Request $request, ProductCatalogue $catalogue)
    {
        abort_unless(
            $this->can($request, self::PAGE_REQUEST, 'create') || $this->can($request, self::PAGE_REQUEST, 'edit'),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $validated = $request->validate([
            'field' => ['required', Rule::in(['code', 'name'])],
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        return response()->json($catalogue->search($validated['field'], $validated['q']));
    }

    /**
     * @return array<string, mixed>
     */
    private function listFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
    }

    private function ownRequests(Request $request, bool $saved): Builder
    {
        return StockRequest::query()
            ->withProgress()
            ->active()
            ->where('isSaved', $saved)
            ->where('created_by', $request->user()->username);
    }

    private function applyFilters(Builder $query, array $filters, bool $searchItems): void
    {
        // Both date bounds are optional and independent, on the created date (legacy).
        if (! empty($filters['date_from'])) {
            $query->where('stock_requests.created_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $query->where('stock_requests.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';

            $query->where(function ($query) use ($like, $searchItems) {
                foreach (['transaction_no', 'origin', 'dept', 'cost_code', 'status'] as $column) {
                    $query->orWhere("stock_requests.{$column}", 'like', $like);
                }
                // Dates need a cast before LIKE on SQL Server.
                $query->orWhereRaw('CAST(stock_requests.date_filed AS VARCHAR(20)) LIKE ?', [$like])
                    ->orWhereRaw('CAST(stock_requests.date_needed AS VARCHAR(20)) LIKE ?', [$like]);

                if ($searchItems) {
                    $query->orWhereExists(function ($items) use ($like) {
                        $items->selectRaw('1')->from('requested_items')
                            ->whereColumn('requested_items.transaction_no', 'stock_requests.transaction_no')
                            ->whereNull('requested_items.deleted_at')
                            ->where(fn ($item) => $item->where('requested_items.description', 'like', $like)
                                ->orWhere('requested_items.stock_code', 'like', $like));
                    });
                }
            });
        }

        $sort = $filters['sort'] ?? 'id';
        $query->orderBy("stock_requests.{$sort}", $filters['direction'] ?? 'desc');

        // Stable paging tie-breaker. SQL Server rejects the same column twice in ORDER BY.
        if ($sort !== 'id') {
            $query->orderByDesc('stock_requests.id');
        }
    }

    /**
     * Legacy "Created By" stamp, e.g. "IT/COMMUNICATIONS_admin_08/04/2026 07:34 PM".
     */
    private function requestedByStamp(Request $request): string
    {
        $user = $request->user();

        return $user->dept.'_'.$user->username.'_'.now()->format('m/d/Y h:i A');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceItems(StockRequest $stockRequest, array $items, string $username): void
    {
        // Legacy rewrites the whole item list on every save.
        RequestedItem::where('transaction_no', $stockRequest->transaction_no)->delete();

        foreach ($items as $item) {
            RequestedItem::create([
                ...$item,
                'requested_by' => $stockRequest->requested_by,
                'created_by' => $username,
                'transaction_no' => $stockRequest->transaction_no,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(StockRequest $row): array
    {
        return [
            'id' => $row->id,
            'transaction_no' => $row->transaction_no,
            'origin' => $row->origin,
            'dept' => $row->dept,
            'cost_code' => $row->cost_code,
            'date_filed' => $row->date_filed,
            'time_filed' => $this->cleanTime($row->time_filed),
            'date_needed' => $row->date_needed,
            'status' => $row->status,
            'isReceived' => (bool) $row->isReceived,
            'completed' => (int) $row->is_completed === 1,
            'unserved' => (int) $row->is_unserved === 1,
            'badge' => $row->statusBadge(),
            'editable' => $row->isEditable(),
            'pending' => $row->isPending(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(StockRequest $stockRequest): array
    {
        return [
            ...$stockRequest->only([
                'id', 'transaction_no', 'date_filed', 'date_needed', 'dept', 'origin', 'requestor',
                'requested_by', 'remarks', 'status', 'approved_by', 'approved_at', 'received_by', 'received_at',
            ]),
            'time_filed' => $this->cleanTime($stockRequest->time_filed),
            'badge' => isset($stockRequest->is_completed) ? $stockRequest->statusBadge() : null,
        ];
    }

    private function cleanTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function csv(string $filename, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, ['Transaction #', 'Origin', 'Date Created', 'Time', 'Department', 'Date Needed', 'Status']);

            foreach ($rows as $row) {
                $badge = $row->statusBadge();
                fputcsv($out, [
                    $row->transaction_no, $row->origin, $row->date_filed, $this->cleanTime($row->time_filed),
                    $row->dept, $row->date_needed,
                    isset($badge['note']) ? "{$badge['label']} ({$badge['note']})" : $badge['label'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function can(Request $request, string $page, string $action): bool
    {
        return $this->accessRights->can($request->user(), $page, $action);
    }

    private function ensureOwn(Request $request, StockRequest $stockRequest): void
    {
        abort_unless(
            $stockRequest->active && strcasecmp((string) $stockRequest->created_by, (string) $request->user()->username) === 0,
            HttpResponse::HTTP_NOT_FOUND,
        );
    }

    private function ensureOwnEditable(Request $request, StockRequest $stockRequest): void
    {
        $this->ensureOwn($request, $stockRequest);

        abort_unless($stockRequest->isEditable(), HttpResponse::HTTP_FORBIDDEN, 'This request can no longer be changed.');
    }

    /**
     * Requestors see their own requests and their department's (the dashboard lists the whole department).
     */
    private function ensureVisible(Request $request, StockRequest $stockRequest): void
    {
        $user = $request->user();

        abort_unless(
            $stockRequest->active && (
                $user->isAdmin()
                || strcasecmp((string) $stockRequest->created_by, (string) $user->username) === 0
                || strcasecmp((string) $stockRequest->dept, (string) $user->dept) === 0
            ),
            HttpResponse::HTTP_NOT_FOUND,
        );
    }
}
