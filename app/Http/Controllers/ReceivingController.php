<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersStockRequests;
use App\Models\IssuedItem;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Services\AccessRightService;
use App\Services\ProductCatalogue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The receiver side (legacy MCD Receiving): fully approved requests addressed to the user's
 * department as the origin are received, then issued item by item until nothing is owed.
 */
class ReceivingController extends Controller
{
    use FiltersStockRequests;

    public const PAGE_MANAGE = 'Manage Receiving';

    public const PAGE_COMPLETED = 'Completed Request';

    private const SORTABLE = ['transaction_no', 'origin', 'date_filed', 'time_filed', 'dept', 'date_needed', 'status'];

    public function __construct(protected AccessRightService $accessRights)
    {
    }

    public function index(Request $request): Response
    {
        return $this->list($request, completed: false);
    }

    public function completed(Request $request): Response
    {
        return $this->list($request, completed: true);
    }

    public function export(Request $request): StreamedResponse
    {
        $completed = $request->boolean('completed');

        abort_unless($this->can($request, $completed ? self::PAGE_COMPLETED : self::PAGE_MANAGE, 'view'), HttpResponse::HTTP_FORBIDDEN);

        $filters = $this->listFilters($request, self::SORTABLE);
        $rows = $this->incoming($request, $completed)
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: false))
            ->get();

        $columns = [
            'Transaction #' => fn ($row) => $row->transaction_no,
            'Date Requested' => fn ($row) => $row->date_filed,
            'Time Requested' => fn ($row) => $this->cleanTime($row->time_filed),
            'Department' => fn ($row) => $row->dept,
            'Date Needed' => fn ($row) => $row->date_needed,
        ];

        if (! $completed) {
            $columns['Status'] = fn ($row) => $row->receivingBadge()['label'];
            $columns['Unserved'] = fn ($row) => (int) $row->has_unserved === 1 ? 'Yes' : 'No';
        }

        return $this->csvDownload($completed ? 'mcd-completed.csv' : 'mcd-receiving.csv', $rows, $columns);
    }

    /**
     * Acknowledge an approved request; only then can items be issued against it.
     */
    public function receive(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->ensureIncoming($request, $stockRequest);

        if ($stockRequest->isReceived) {
            return back()->with('error', "{$stockRequest->transaction_no} has already been received.");
        }

        $stockRequest->update([
            'received_by' => $request->user()->username,
            'isReceived' => true,
            'received_at' => now(),
        ]);

        return back()->with('success', "{$stockRequest->transaction_no} has been received.");
    }

    public function show(Request $request, StockRequest $stockRequest): Response
    {
        abort_unless(
            $this->can($request, self::PAGE_MANAGE, 'view') || $this->can($request, self::PAGE_COMPLETED, 'view'),
            HttpResponse::HTTP_FORBIDDEN,
        );
        $this->ensureIncoming($request, $stockRequest);

        return Inertia::render('Receiving/Show', [
            'stockRequest' => $this->detail($stockRequest),
            'items' => $this->items($stockRequest)->map(fn (RequestedItem $item) => [
                ...$this->itemRow($item),
                'serve' => $item->serveStatus(),
            ]),
            'backTo' => $request->query('from') === 'completed' ? 'mcds.completed' : 'mcds.index',
        ]);
    }

    /**
     * The issuance screen: every item that still has a balance and is not closed.
     */
    public function edit(Request $request, StockRequest $stockRequest): Response|RedirectResponse
    {
        $this->ensureIncoming($request, $stockRequest);

        if (! $stockRequest->isReceived) {
            return redirect()->route('mcds.index')->with('error', 'Receive this request before issuing items.');
        }

        $items = $this->items($stockRequest)
            ->filter(fn (RequestedItem $item) => (int) $item->isClosed !== 1 && (int) $item->requested_qty - (int) $item->issued_qty !== 0)
            ->values();

        $history = IssuedItem::query()
            ->whereIn('item_id', $items->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('item_id');

        return Inertia::render('Receiving/Edit', [
            'stockRequest' => $this->detail($stockRequest),
            'items' => $items->map(fn (RequestedItem $item) => [
                ...$this->itemRow($item),
                'history' => ($history[$item->id] ?? collect())->map(fn (IssuedItem $issuance) => [
                    'id' => $issuance->id,
                    'item_code' => $issuance->item_code,
                    'issuance_qty' => $issuance->issuance_qty,
                    'balance' => $issuance->balance,
                    'received_by' => $issuance->received_by,
                    'issued_by' => $issuance->issued_by,
                    'date' => $issuance->created_at?->format('Y-m-d'),
                    'time' => $issuance->created_at?->format('h:i A'),
                ])->values(),
            ]),
        ]);
    }

    /**
     * Record the quantities issued now. Blank rows are skipped; nothing may exceed the balance.
     */
    public function issue(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->ensureIncoming($request, $stockRequest);
        abort_unless($stockRequest->isReceived, HttpResponse::HTTP_FORBIDDEN, 'Receive this request before issuing items.');

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.issuance_qty' => ['nullable', 'integer', 'min:1'],
        ], [
            'items.*.issuance_qty.integer' => 'Input numeric characters only!',
            'items.*.issuance_qty.min' => 'Issuance Quantity cannot be 0/negative!',
        ]);

        $quantities = collect($validated['items'])
            ->filter(fn ($row) => ! empty($row['issuance_qty']))
            ->mapWithKeys(fn ($row) => [(int) $row['id'] => (int) $row['issuance_qty']]);

        if ($quantities->isEmpty()) {
            return redirect()->route('mcds.index');
        }

        DB::transaction(function () use ($stockRequest, $quantities, $request) {
            $items = $stockRequest->items()->withIssued()->lockForUpdate()
                ->whereIn('requested_items.id', $quantities->keys())
                ->get()
                ->keyBy('id');

            foreach ($quantities as $id => $qty) {
                $item = $items[$id] ?? null;

                if (! $item || (int) $item->isClosed === 1) {
                    throw ValidationException::withMessages(["items.{$id}" => 'An item is no longer open for issuance. Reload the page.']);
                }

                $balance = (int) $item->requested_qty - (int) $item->issued_qty;

                if ($qty > $balance) {
                    throw ValidationException::withMessages([
                        "items.{$id}" => "Overissue: Maximum issuance quantity for {$item->stock_code} is {$balance} {$item->uom}/s only!",
                    ]);
                }

                IssuedItem::create([
                    'item_id' => $item->id,
                    'item_code' => $item->stock_code,
                    'issuance_qty' => $qty,
                    'balance' => $balance - $qty,
                    'received_by' => $stockRequest->requested_by,
                    'issued_by' => $request->user()->username,
                ]);
            }
        });

        return redirect()->route('mcds.index')->with('success', "Issuance for {$stockRequest->transaction_no} has been saved.");
    }

    /**
     * Force-close an item that will not be (fully) served; a reason is required.
     */
    public function close(Request $request, RequestedItem $item): RedirectResponse
    {
        $stockRequest = $this->requestOf($item);
        $this->ensureIncoming($request, $stockRequest);

        $validated = $request->validate(
            ['remarks' => ['required', 'string', 'max:255']],
            ['remarks.required' => "Whoops! Closing cannot proceed without specifying a reason."],
        );

        $item->update([
            'updated_by' => $request->user()->username,
            'isClosed' => 1,
            'isClosed_remarks' => trim($validated['remarks']),
        ]);

        return back()->with('success', "Item ({$item->id}) {$item->description} is closed.");
    }

    /**
     * Exclude (soft-delete) an item from the request.
     */
    public function exclude(Request $request, RequestedItem $item): RedirectResponse
    {
        $stockRequest = $this->requestOf($item);
        $this->ensureIncoming($request, $stockRequest);

        $item->update([
            'deleted_at' => now(),
            'deleted_by' => $request->user()->username,
        ]);

        return back()->with('success', 'Item excluded!');
    }

    public function print(Request $request, StockRequest $stockRequest): HttpResponse
    {
        abort_unless(
            $this->can($request, self::PAGE_MANAGE, 'view') || $this->can($request, self::PAGE_COMPLETED, 'view'),
            HttpResponse::HTTP_FORBIDDEN,
        );
        $this->ensureIncoming($request, $stockRequest);

        $items = $this->items($stockRequest);
        $products = app(ProductCatalogue::class)->findByCodes($items->pluck('stock_code')->filter()->unique()->values()->all());

        return Pdf::loadView('reports.stock-request-issuer', [
            'stockRequest' => $stockRequest,
            'requested_items' => $items,
            'products' => $products,
        ])->setPaper('legal', 'portrait')->download("ReceivedStockRequest#{$stockRequest->transaction_no}.pdf");
    }

    private function list(Request $request, bool $completed): Response
    {
        $filters = $this->listFilters($request, self::SORTABLE);

        $requests = $this->incoming($request, $completed)
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: false))
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn (StockRequest $row) => [
                'id' => $row->id,
                'transaction_no' => $row->transaction_no,
                'origin' => $row->origin,
                'dept' => $row->dept,
                'date_filed' => $row->date_filed,
                'time_filed' => $this->cleanTime($row->time_filed),
                'date_needed' => $row->date_needed,
                'isReceived' => (bool) $row->isReceived,
                'badge' => $row->receivingBadge(),
            ]);

        $page = $completed ? self::PAGE_COMPLETED : self::PAGE_MANAGE;

        return Inertia::render($completed ? 'Receiving/Completed' : 'Receiving/Index', [
            'requests' => $requests,
            'filters' => $filters,
            'can' => [
                'view' => $this->can($request, $page, 'view'),
                'edit' => $this->can($request, self::PAGE_MANAGE, 'edit'),
            ],
        ]);
    }

    /**
     * Fully approved requests whose stock comes from the user's department, split the legacy way:
     * a request is finished business only once it is complete and nothing is left unserved.
     */
    private function incoming(Request $request, bool $completed): Builder
    {
        return StockRequest::query()
            ->withProgress()
            ->active()
            ->where('stock_requests.isSaved', true)
            ->where('stock_requests.status', StockRequest::STATUS_FULLY_APPROVED)
            ->where('stock_requests.origin', $request->user()->dept)
            ->when(
                $completed,
                fn ($query) => $query->whereRaw(StockRequest::COMPLETED_SQL)->whereRaw('NOT '.StockRequest::HAS_UNSERVED_SQL),
                fn ($query) => $query->whereRaw('(NOT '.StockRequest::COMPLETED_SQL.' OR '.StockRequest::HAS_UNSERVED_SQL.')'),
            );
    }

    /**
     * @return \Illuminate\Support\Collection<int, RequestedItem>
     */
    private function items(StockRequest $stockRequest)
    {
        return $stockRequest->items()->withIssued()->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRow(RequestedItem $item): array
    {
        return [
            'id' => $item->id,
            'stock_code' => $item->stock_code,
            'description' => $item->description,
            'uom' => $item->uom,
            'available_qty' => $item->available_qty,
            'requested_qty' => (int) $item->requested_qty,
            'issued_qty' => (int) $item->issued_qty,
            'balance' => (int) $item->requested_qty - (int) $item->issued_qty,
            'remarks' => $item->remarks,
            'isClosed' => (int) $item->isClosed === 1,
            'isClosed_remarks' => $item->isClosed_remarks,
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
                'requested_by', 'remarks', 'approved_by', 'approved_at', 'received_by', 'received_at',
            ]),
            'isReceived' => (bool) $stockRequest->isReceived,
            'time_filed' => $this->cleanTime($stockRequest->time_filed),
        ];
    }

    private function requestOf(RequestedItem $item): StockRequest
    {
        abort_if($item->deleted_at !== null, HttpResponse::HTTP_NOT_FOUND);

        $stockRequest = StockRequest::where('transaction_no', $item->transaction_no)->first();
        abort_unless($stockRequest && $stockRequest->isReceived, HttpResponse::HTTP_NOT_FOUND);

        return $stockRequest;
    }

    /**
     * The request must be live, approved and sourced from the receiver's department (admins see all).
     */
    private function ensureIncoming(Request $request, StockRequest $stockRequest): void
    {
        $user = $request->user();

        abort_unless(
            $stockRequest->active
                && $stockRequest->isSaved
                && strcasecmp((string) $stockRequest->status, StockRequest::STATUS_FULLY_APPROVED) === 0
                && ($user->isAdmin() || strcasecmp((string) $stockRequest->origin, (string) $user->dept) === 0),
            HttpResponse::HTTP_NOT_FOUND,
        );
    }

    private function can(Request $request, string $page, string $action): bool
    {
        return $this->accessRights->can($request->user(), $page, $action);
    }
}
