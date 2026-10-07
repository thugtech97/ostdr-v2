<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersStockRequests;
use App\Models\Audit;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Services\AuditService;
use App\Services\ProductCatalogue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports. The two stock request reports are open to every signed-in user (legacy) and
 * scoped to the user's department; the audit and error logs are admin pages.
 */
class ReportController extends Controller
{
    use FiltersStockRequests;

    public const PAGE_AUDIT = 'User Action Monitoring';

    public const PAGE_ERRORS = 'Application Error Logs';

    private const SORTABLE = ['transaction_no', 'origin', 'date_filed', 'dept', 'date_needed', 'status'];

    private const STATUS_FILTERS = ['pending', 'submitted', 'approved', 'received', 'completed', 'cancelled'];

    private const UNSERVED_SORTS = [
        'transaction_no' => 'requested_items.transaction_no',
        'stock_code' => 'requested_items.stock_code',
        'description' => 'requested_items.description',
        'requested_qty' => 'requested_items.requested_qty',
        'issued_total' => RequestedItem::ISSUED_QTY_SQL,
        'date_filed' => 'sr.date_filed',
        'date_needed' => 'sr.date_needed',
        'dept' => 'sr.dept',
        'origin' => 'sr.origin',
        'days_outstanding' => 'sr.created_at',
        'item_state' => 'requested_items.isClosed',
    ];

    private const AUDIT_SORTS = ['username' => 'u.username', 'event' => 'audits.event', 'auditable_type' => 'audits.auditable_type', 'created_at' => 'audits.created_at'];

    private const LOG_SORTS = ['id', 'level', 'level_name', 'message', 'created_at'];

    /**
     * Stock Transfer Requests: the department's saved requests with a status filter.
     */
    public function stockRequests(Request $request): Response
    {
        $filters = $this->stockRequestFilters($request);

        return Inertia::render('Reports/StockRequests', [
            'requests' => $this->stockRequestQuery($request, $filters)
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
                    'badge' => $row->recordsBadge(withNote: true),
                ]),
            'filters' => $filters,
        ]);
    }

    public function exportStockRequests(Request $request): StreamedResponse
    {
        $rows = $this->stockRequestQuery($request, $this->stockRequestFilters($request))->get();

        return $this->csvDownload('stock-transfer-requests.csv', $rows, [
            'Transaction #' => fn ($row) => $row->transaction_no,
            'Origin' => fn ($row) => $row->origin,
            'Date Requested' => fn ($row) => $row->date_filed,
            'Department' => fn ($row) => $row->dept,
            'Date Needed' => fn ($row) => $row->date_needed,
            'Status' => fn ($row) => $row->recordsBadge()['label'],
        ]);
    }

    public function showStockRequest(Request $request, StockRequest $stockRequest): Response
    {
        $this->ensureInReport($request, $stockRequest);

        return Inertia::render('Receiving/Show', [
            'stockRequest' => [
                ...$stockRequest->only([
                    'id', 'transaction_no', 'date_filed', 'date_needed', 'dept', 'origin', 'requestor',
                    'requested_by', 'remarks', 'approved_by', 'approved_at', 'received_by', 'received_at',
                ]),
                'isReceived' => (bool) $stockRequest->isReceived,
                'time_filed' => $this->cleanTime($stockRequest->time_filed),
            ],
            'items' => $stockRequest->items()->withIssued()->get()->map(fn (RequestedItem $item) => [
                'id' => $item->id,
                'stock_code' => $item->stock_code,
                'description' => $item->description,
                'uom' => $item->uom,
                'requested_qty' => (int) $item->requested_qty,
                'issued_qty' => (int) $item->issued_qty,
                'serve' => $item->serveStatus(),
            ]),
            'backTo' => 'reports.stock-requests',
            'printRoute' => 'reports.stock-requests.print',
        ]);
    }

    public function printStockRequest(Request $request, StockRequest $stockRequest): HttpResponse
    {
        $this->ensureInReport($request, $stockRequest);

        $items = $stockRequest->items()->withIssued()->get();
        $products = app(ProductCatalogue::class)->findByCodes($items->pluck('stock_code')->filter()->unique()->values()->all());

        return Pdf::loadView('reports.stock-request-issuer', [
            'stockRequest' => $stockRequest,
            'requested_items' => $items,
            'products' => $products,
        ])->setPaper('legal', 'portrait')->download("ReceivedStockRequest#{$stockRequest->transaction_no}.pdf");
    }

    /**
     * Unserved Transactions: every requested line still owed, for the department that asked
     * for it and the one that owes it. Closed items stay in: they are only off MCD's desk.
     */
    public function unserved(Request $request): Response
    {
        $filters = $this->unservedFilters($request);

        return Inertia::render('Reports/Unserved', [
            'items' => $this->unservedQuery($request, $filters)
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
                ->through(fn (RequestedItem $item) => $this->unservedRow($item)),
            'filters' => $filters,
        ]);
    }

    public function exportUnserved(Request $request): StreamedResponse
    {
        $rows = $this->unservedQuery($request, $this->unservedFilters($request))->get()
            ->map(fn (RequestedItem $item) => $this->unservedRow($item));

        return $this->csvDownload('unserved-transactions.csv', $rows, [
            'Transaction #' => fn ($row) => $row['transaction_no'],
            'Date Requested' => fn ($row) => $row['date_filed'],
            'Date Needed' => fn ($row) => $row['date_needed'],
            'Department' => fn ($row) => $row['dept'],
            'Origin' => fn ($row) => $row['origin'],
            'Cost Code' => fn ($row) => $row['cost_code'],
            'Stock Code' => fn ($row) => $row['stock_code'],
            'Description' => fn ($row) => $row['description'],
            'UOM' => fn ($row) => $row['uom'],
            'Requested Qty' => fn ($row) => $row['requested_qty'],
            'Issued Qty' => fn ($row) => $row['issued_total'],
            'Unserved Qty' => fn ($row) => $row['unserved_qty'],
            'Item' => fn ($row) => $row['isClosed'] ? 'Closed' : 'Open',
            'Closing Remarks' => fn ($row) => $row['isClosed_remarks'],
            'Request Status' => fn ($row) => $row['request_status'],
            'Days Outstanding' => fn ($row) => $row['days_outstanding'],
        ]);
    }

    /**
     * User Action Monitoring: the audit trail, today's by default.
     */
    public function auditLogs(Request $request, AuditService $audit): Response
    {
        $filters = $this->logFilters($request, array_keys(self::AUDIT_SORTS));

        $logs = $this->auditQuery($filters)
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn (Audit $row) => [
                'id' => $row->id,
                'auditable_type' => $row->auditable_type,
                'auditable_id' => $row->auditable_id,
                'event' => $row->event,
                'username' => $row->username,
                'old_values' => $this->json($row->getRawOriginal('old_values')),
                'new_values' => $this->json($row->getRawOriginal('new_values')),
                'created_at' => $row->created_at?->format('Y-m-d h:i:s A'),
            ]);

        $audit->report($request, 'Audit Logs');

        return Inertia::render('Reports/AuditLogs', ['logs' => $logs, 'filters' => $filters]);
    }

    public function exportAuditLogs(Request $request): StreamedResponse
    {
        return $this->csvDownload('audit-logs.csv', $this->auditQuery($this->logFilters($request, array_keys(self::AUDIT_SORTS)))->cursor(), [
            'Model' => fn ($row) => $row->auditable_type,
            'Action' => fn ($row) => $row->event,
            'User' => fn ($row) => $row->username,
            'Old Value' => fn ($row) => $this->json($row->getRawOriginal('old_values')),
            'New Value' => fn ($row) => $this->json($row->getRawOriginal('new_values')),
            'Activity Date' => fn ($row) => $row->created_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Application Error Logs: the `log` table, today's by default.
     */
    public function errorLogs(Request $request, AuditService $audit): Response
    {
        $filters = $this->logFilters($request, self::LOG_SORTS);

        $logs = $this->errorQuery($filters)
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => $row->id,
                'message' => $row->message,
                'level' => $row->level,
                'level_name' => $row->level_name,
                'username' => $row->username,
                'datetime' => $row->datetime ?: $row->created_at,
            ]);

        $audit->report($request, 'Error Logs');

        return Inertia::render('Reports/ErrorLogs', ['logs' => $logs, 'filters' => $filters]);
    }

    public function exportErrorLogs(Request $request): StreamedResponse
    {
        return $this->csvDownload('error-logs.csv', $this->errorQuery($this->logFilters($request, self::LOG_SORTS))->cursor(), [
            'ID' => fn ($row) => $row->id,
            'Message' => fn ($row) => $row->message,
            'Level' => fn ($row) => $row->level,
            'Level Name' => fn ($row) => $row->level_name,
            'Date' => fn ($row) => $row->datetime ?: $row->created_at,
        ]);
    }

    public function manual(): Response
    {
        return Inertia::render('Reports/UserManual', ['url' => asset('User-Manual.pdf')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stockRequestFilters(Request $request): array
    {
        return [
            ...$this->listFilters($request, self::SORTABLE),
            ...$request->validate(['status' => ['nullable', Rule::in(self::STATUS_FILTERS)]]),
        ];
    }

    private function stockRequestQuery(Request $request, array $filters): Builder
    {
        $notReceived = '(stock_requests.isReceived = 0 OR stock_requests.isReceived IS NULL)';

        return StockRequest::query()
            ->withProgress()
            ->active()
            ->where('stock_requests.isSaved', true)
            ->where('stock_requests.dept', $request->user()->dept)
            ->when($filters['status'] ?? null, fn ($query, $status) => match ($status) {
                'approved' => $query->where('stock_requests.status', StockRequest::STATUS_FULLY_APPROVED)->whereRaw($notReceived),
                'received' => $query->where('stock_requests.isReceived', true)->whereRaw('NOT '.StockRequest::COMPLETED_SQL),
                'completed' => $query->where('stock_requests.isReceived', true)->whereRaw(StockRequest::COMPLETED_SQL),
                default => $query->where('stock_requests.status', $status),
            })
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function unservedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'served' => ['nullable', Rule::in(['unserved', 'partial', 'all'])],
            'item_state' => ['nullable', Rule::in(['all', 'open', 'closed'])],
            'sort' => ['nullable', Rule::in(array_keys(self::UNSERVED_SORTS))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
    }

    private function unservedQuery(Request $request, array $filters): Builder
    {
        $user = $request->user();
        $issued = RequestedItem::ISSUED_QTY_SQL;

        $query = RequestedItem::query()
            ->join('stock_requests as sr', 'sr.transaction_no', '=', 'requested_items.transaction_no')
            ->whereNull('requested_items.deleted_at')
            ->where('sr.isSaved', true)
            ->where('sr.active', true)
            ->select('requested_items.*')
            ->selectRaw("{$issued} AS issued_total")
            ->addSelect(['sr.date_filed', 'sr.date_needed', 'sr.dept', 'sr.origin', 'sr.cost_code', 'sr.isReceived', 'sr.status as request_status', 'sr.created_at as requested_at']);

        if (! $user->isAdmin()) {
            $query->where(fn ($scope) => $scope->where('sr.dept', $user->dept)->orWhere('sr.origin', $user->dept));
        }

        if (! empty($filters['date_from'])) {
            $query->where('sr.created_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $query->where('sr.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        match ($filters['served'] ?? 'unserved') {
            'partial' => $query->whereRaw("{$issued} > 0")->whereRaw("{$issued} < requested_items.requested_qty"),
            'all' => $query->whereRaw("{$issued} < requested_items.requested_qty"),
            default => $query->whereRaw("{$issued} = 0"),
        };

        match ($filters['item_state'] ?? 'all') {
            'open' => $query->whereRaw('COALESCE(requested_items.isClosed, 0) <> 1'),
            'closed' => $query->where('requested_items.isClosed', 1),
            default => null,
        };

        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($query) => $query->where('requested_items.transaction_no', 'like', $like)
                ->orWhere('requested_items.stock_code', 'like', $like)
                ->orWhere('requested_items.description', 'like', $like)
                ->orWhere('sr.dept', 'like', $like)
                ->orWhere('sr.origin', 'like', $like));
        }

        $sort = $filters['sort'] ?? null;
        $sort
            ? $query->orderByRaw(self::UNSERVED_SORTS[$sort].' '.($filters['direction'] ?? 'asc'))
            : $query->orderByDesc('sr.created_at');

        return $query->orderByDesc('requested_items.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function unservedRow(RequestedItem $item): array
    {
        return [
            'id' => $item->id,
            'transaction_no' => $item->transaction_no,
            'date_filed' => $item->date_filed,
            'date_needed' => $item->date_needed,
            'dept' => $item->dept,
            'origin' => $item->origin,
            'cost_code' => $item->cost_code,
            'stock_code' => $item->stock_code,
            'description' => $item->description,
            'uom' => $item->uom,
            'requested_qty' => (int) $item->requested_qty,
            'issued_total' => (int) $item->issued_total,
            'unserved_qty' => max(0, (int) $item->requested_qty - (int) $item->issued_total),
            'isClosed' => (int) $item->isClosed === 1,
            'isClosed_remarks' => $item->isClosed_remarks,
            'request_status' => $item->request_status,
            'days_outstanding' => $item->requested_at ? (int) Carbon::parse($item->requested_at)->startOfDay()->diffInDays(now()->startOfDay()) : null,
        ];
    }

    /**
     * Legacy opens the logs on today unless a range is given.
     *
     * @param  list<string>  $sortable
     * @return array<string, mixed>
     */
    private function logFilters(Request $request, array $sortable): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in($sortable)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);

        return [
            ...$filters,
            'date_from' => $filters['date_from'] ?? now()->toDateString(),
            'date_to' => $filters['date_to'] ?? now()->toDateString(),
        ];
    }

    private function auditQuery(array $filters): Builder
    {
        $query = Audit::query()
            ->leftJoin('users as u', 'audits.user_id', '=', 'u.id')
            ->select('audits.*', 'u.username')
            ->whereBetween('audits.created_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);

        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($query) => $query->where('u.username', 'like', $like)
                ->orWhere('audits.event', 'like', $like)
                ->orWhere('audits.auditable_type', 'like', $like));
        }

        $sort = $filters['sort'] ?? 'created_at';
        $query->orderBy(self::AUDIT_SORTS[$sort], $filters['direction'] ?? 'desc');

        return $query->orderByDesc('audits.id');
    }

    private function errorQuery(array $filters)
    {
        $query = DB::table('log')->whereBetween('created_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);

        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($query) => $query->where('message', 'like', $like)->orWhere('level_name', 'like', $like)->orWhere('username', 'like', $like));
        }

        $sort = $filters['sort'] ?? 'created_at';
        $query->orderBy($sort, $filters['direction'] ?? 'desc');

        return $sort === 'id' ? $query : $query->orderByDesc('id');
    }

    private function json(?string $value): string
    {
        return in_array($value, [null, '', '[]', '{}'], true) ? '' : $value;
    }

    private function ensureInReport(Request $request, StockRequest $stockRequest): void
    {
        $user = $request->user();

        abort_unless(
            $stockRequest->active && $stockRequest->isSaved
                && ($user->isAdmin() || strcasecmp((string) $stockRequest->dept, (string) $user->dept) === 0),
            HttpResponse::HTTP_NOT_FOUND,
        );
    }
}
