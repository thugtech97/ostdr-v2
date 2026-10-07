<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersStockRequests;
use App\Models\Batch;
use App\Models\RequestedItem;
use App\Models\StockRequest;
use App\Services\AccessRightService;
use App\Services\ProductCatalogue;
use App\Services\StockListReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * MCD Records: the completed-transaction archive, batches of issued items, the unbatched /
 * unserved item reports and the catalogue "Update Items" upload. Not scoped to a department.
 */
class McdRecordController extends Controller
{
    use FiltersStockRequests;

    public const PAGE_COMPLETED = 'Completed Dashboard';

    public const PAGE_BATCHES = 'Manage Batches';

    public const PAGE_ITEMS = 'Item Updating';

    private const SORTABLE = ['transaction_no', 'origin', 'date_filed', 'dept', 'date_needed', 'status'];

    private const BATCH_SORTABLE = ['batchno', 'description', 'created_at'];

    private const ITEM_SORTABLE = ['transaction_no', 'stock_code', 'description', 'requested_qty', 'issued_qty', 'remarks'];

    private const PRODUCT_SORTABLE = ['code', 'name', 'uom', 'oem'];

    public function __construct(protected AccessRightService $accessRights)
    {
    }

    /**
     * Completed Transactions: every completed request, optionally only the fully batched ones.
     */
    public function index(Request $request): Response
    {
        $filters = $this->completedFilters($request);

        $requests = $this->completedRequests($filters)
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        $batchNumbers = $this->batchNumbers($requests->getCollection()->pluck('transaction_no'));

        return Inertia::render('McdRecords/Index', [
            'requests' => $requests->through(fn (StockRequest $row) => [
                'id' => $row->id,
                'transaction_no' => $row->transaction_no,
                'date_filed' => $row->date_filed,
                'dept' => $row->dept,
                'origin' => $row->origin,
                'batch_numbers' => $batchNumbers[$row->transaction_no] ?? null,
                'isReceived' => (bool) $row->isReceived,
                'badge' => $row->recordsBadge(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->completedFilters($request);
        $rows = $this->completedRequests($filters)->get();
        $batchNumbers = $this->batchNumbers($rows->pluck('transaction_no'));

        return $this->csvDownload('mcd-completed-requests.csv', $rows, [
            'Transaction #' => fn ($row) => $row->transaction_no,
            'Date Requested' => fn ($row) => $row->date_filed,
            'Department' => fn ($row) => $row->dept,
            'Origin' => fn ($row) => $row->origin,
            'Batch No.' => fn ($row) => $batchNumbers[$row->transaction_no] ?? '',
            'Status' => fn ($row) => $row->recordsBadge()['label'],
        ]);
    }

    public function showRequest(StockRequest $stockRequest): Response
    {
        abort_unless($stockRequest->active && $stockRequest->isSaved, HttpResponse::HTTP_NOT_FOUND);

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
                ...$this->itemRow($item),
                'serve' => $item->serveStatus(),
            ]),
            'backTo' => 'mcdrecords.index',
            'printRoute' => 'mcdrecords.print',
        ]);
    }

    public function printRequest(StockRequest $stockRequest): HttpResponse
    {
        abort_unless($stockRequest->active && $stockRequest->isSaved, HttpResponse::HTTP_NOT_FOUND);

        $items = $stockRequest->items()->withIssued()->get();
        $products = app(ProductCatalogue::class)->findByCodes($items->pluck('stock_code')->filter()->unique()->values()->all());

        return Pdf::loadView('reports.stock-request-issuer', [
            'stockRequest' => $stockRequest,
            'requested_items' => $items,
            'products' => $products,
        ])->setPaper('legal', 'portrait')->download("ReceivedStockRequest#{$stockRequest->transaction_no}.pdf");
    }

    public function batches(Request $request): Response
    {
        $filters = $this->batchFilters($request);

        return Inertia::render('McdRecords/Batches', [
            'batches' => $this->batchQuery($filters)
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
                ->through(fn (Batch $batch) => $this->batchRow($batch)),
            'filters' => $filters,
            'can' => [
                'create' => $this->can($request, self::PAGE_BATCHES, 'create'),
                'edit' => $this->can($request, self::PAGE_BATCHES, 'edit'),
                'delete' => $this->can($request, self::PAGE_BATCHES, 'delete'),
            ],
        ]);
    }

    public function exportBatches(Request $request): StreamedResponse
    {
        abort_unless($this->can($request, self::PAGE_BATCHES, 'view'), HttpResponse::HTTP_FORBIDDEN);

        return $this->csvDownload('batches.csv', $this->batchQuery($this->batchFilters($request))->get(), [
            'Batch #' => fn ($batch) => $batch->batchno,
            'Description' => fn ($batch) => $batch->description,
            'Date Created' => fn ($batch) => $batch->created_at?->format('Y-m-d h:i A'),
        ]);
    }

    /**
     * New batch: pick completed requests, then tick the issued items to put in the batch.
     * The items load as a partial reload once requests are chosen (?transactions[]=...).
     */
    public function create(Request $request): Response
    {
        $transactions = $this->selectedTransactions($request);

        return Inertia::render('McdRecords/BatchCreate', [
            'requests' => fn () => $this->batchableRequests(),
            'transactions' => $transactions,
            'items' => fn () => $this->batchableItems($transactions)->map(fn (RequestedItem $item) => $this->itemRow($item)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batchno' => ['required', 'string', 'max:50', Rule::unique('batches', 'batchno')],
            'description' => ['required', 'string', 'max:255'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ], [
            'batchno.required' => 'The Batch No field is required.',
            'batchno.unique' => 'The Batch No must be unique',
            'description.required' => 'The Description field is required.',
            'item_ids.required' => 'Check at least 1 stock request',
            'item_ids.min' => 'Check at least 1 stock request',
        ]);

        DB::transaction(function () use ($validated, $request) {
            Batch::create([
                'batchno' => $validated['batchno'],
                'description' => $validated['description'],
                'created_by' => $request->user()->username,
            ]);

            // Only items that are still free to batch, whatever ids were posted.
            RequestedItem::query()->live()->whereNull('batchno')->whereIn('id', $validated['item_ids'])
                ->get()
                ->each(fn (RequestedItem $item) => $item->update(['batchno' => $validated['batchno']]));
        });

        return redirect()->route('mcdrecords.batches')->with('success', "Batch {$validated['batchno']} has been created.");
    }

    public function show(Batch $batch): Response
    {
        $this->ensureLive($batch);

        return Inertia::render('McdRecords/BatchShow', [
            'batch' => $this->batchRow($batch),
            'items' => $this->batchItems($batch),
        ]);
    }

    public function edit(Batch $batch): Response
    {
        $this->ensureLive($batch);

        return Inertia::render('McdRecords/BatchEdit', [
            'batch' => $this->batchRow($batch),
            'items' => $this->batchItems($batch),
            'requests' => $this->batchableRequests(),
        ]);
    }

    public function update(Request $request, Batch $batch): RedirectResponse
    {
        $this->ensureLive($batch);

        $validated = $request->validate([
            'batchno' => ['required', 'string', 'max:50', Rule::unique('batches', 'batchno')->ignore($batch->id)],
            'description' => ['required', 'string', 'max:255'],
        ], [
            'batchno.required' => 'The Batch No field is required.',
            'batchno.unique' => 'The Batch No is already taken.',
            'description.required' => 'The Description field is required.',
        ]);

        DB::transaction(function () use ($batch, $validated, $request) {
            $old = $batch->batchno;

            $batch->update([
                'batchno' => $validated['batchno'],
                'description' => $validated['description'],
                'updated_by' => $request->user()->username,
            ]);

            if ($old !== $validated['batchno']) {
                RequestedItem::where('batchno', $old)->update(['batchno' => $validated['batchno']]);
            }
        });

        return redirect()->route('mcdrecords.batches')->with('success', 'Batch has been updated.');
    }

    /**
     * Add the batchable items of more completed requests to this batch.
     */
    public function include(Request $request, Batch $batch): RedirectResponse
    {
        $this->ensureLive($batch);

        $validated = $request->validate([
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*' => ['string', 'max:50'],
        ]);

        $this->batchableItems($validated['transactions'])
            ->each(fn (RequestedItem $item) => $item->update(['batchno' => $batch->batchno]));

        return back()->with('success', 'Items added to the batch.');
    }

    public function exclude(Batch $batch, RequestedItem $item): RedirectResponse
    {
        $this->ensureLive($batch);
        abort_unless($item->batchno === $batch->batchno, HttpResponse::HTTP_NOT_FOUND);

        $item->update(['batchno' => null]);

        return back()->with('success', 'Item excluded!');
    }

    public function destroy(Batch $batch): RedirectResponse
    {
        $this->ensureLive($batch);

        DB::transaction(function () use ($batch) {
            RequestedItem::where('batchno', $batch->batchno)->update(['batchno' => null]);
            $batch->delete();
        });

        return back()->with('success', 'Batch has been deleted.');
    }

    public function unbatched(Request $request): Response
    {
        return $this->itemReport($request, 'unbatched');
    }

    public function unserved(Request $request): Response
    {
        return $this->itemReport($request, 'unserved');
    }

    public function exportItems(Request $request, string $report): StreamedResponse
    {
        abort_unless($this->can($request, self::PAGE_BATCHES, 'view'), HttpResponse::HTTP_FORBIDDEN);

        return $this->csvDownload("{$report}-items.csv", $this->itemQuery($report, $this->itemFilters($request))->get(), [
            'Transaction No' => fn ($item) => $item->transaction_no,
            'Stock Code' => fn ($item) => $item->stock_code,
            'Description' => fn ($item) => $item->description,
            'Requested Qty' => fn ($item) => $item->requested_qty,
            'Issued Qty' => fn ($item) => (int) $item->issued_qty,
            'Remarks' => fn ($item) => $item->remarks,
        ]);
    }

    /**
     * Catalogue Items: the published catalogue plus the stock list upload.
     */
    public function catalogue(Request $request, ProductCatalogue $catalogue): Response
    {
        $filters = $this->productFilters($request);

        return Inertia::render('McdRecords/Catalogue', [
            'products' => $catalogue->browse($filters)->withQueryString(),
            'filters' => $filters,
            'can' => ['upload' => $this->can($request, self::PAGE_ITEMS, 'create')],
        ]);
    }

    public function exportCatalogue(Request $request, ProductCatalogue $catalogue): StreamedResponse
    {
        abort_unless($this->can($request, self::PAGE_ITEMS, 'view'), HttpResponse::HTTP_FORBIDDEN);

        $products = $catalogue->browse([...$this->productFilters($request), 'per_page' => 100000])->items();

        return $this->csvDownload('catalogue-items.csv', $products, [
            'Stock Code' => fn ($row) => $row['code'],
            'Description' => fn ($row) => $row['name'],
            'UoM' => fn ($row) => $row['uom'],
            'OEM' => fn ($row) => $row['oem'],
        ]);
    }

    public function uploadStocks(Request $request, StockListReader $reader, ProductCatalogue $catalogue): RedirectResponse
    {
        $request->validate([
            'itemFile' => ['required', 'file', 'extensions:xlsx,xls', 'max:20480'],
        ], [
            'itemFile.required' => 'No file uploaded.',
            'itemFile.extensions' => 'Invalid file format. Only .xls .xlsx files are allowed.',
        ]);

        try {
            $rows = $reader->read($request->file('itemFile'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['itemFile' => $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withErrors(['itemFile' => 'Error reading the Excel file: '.$e->getMessage()]);
        }

        $counts = $catalogue->importStockList($rows, $request->user()->id);

        return back()->with('success', "Catalogue items updated! ({$counts['updated']} updated, {$counts['created']} added)");
    }

    /**
     * @return array<string, mixed>
     */
    private function completedFilters(Request $request): array
    {
        return [
            ...$this->listFilters($request, self::SORTABLE),
            ...$request->validate(['batch_status' => ['nullable', Rule::in(['all-batched'])]]),
        ];
    }

    private function completedRequests(array $filters): Builder
    {
        return StockRequest::query()
            ->withProgress()
            ->active()
            ->where('stock_requests.isSaved', true)
            ->whereRaw(StockRequest::COMPLETED_SQL)
            ->when(($filters['batch_status'] ?? null) === 'all-batched', fn ($query) => $query->whereRaw(StockRequest::ALL_BATCHED_SQL))
            ->tap(fn ($query) => $this->applyFilters($query, $filters, searchItems: true));
    }

    /**
     * Distinct batch numbers of each request's live items, "|"-joined as legacy shows them.
     *
     * @return array<string, string>
     */
    private function batchNumbers(Collection $transactionNos): array
    {
        return RequestedItem::query()->live()
            ->whereIn('transaction_no', $transactionNos->filter()->all())
            ->whereNotNull('batchno')
            ->orderByDesc('id')
            ->get(['transaction_no', 'batchno'])
            ->groupBy('transaction_no')
            ->map(fn ($items) => $items->pluck('batchno')->unique()->implode('|'))
            ->all();
    }

    /**
     * Requests with something left to batch: completed or not, at least one item fully issued.
     *
     * @return Collection<int, array{transaction_no: string, dept: ?string}>
     */
    private function batchableRequests(): Collection
    {
        return StockRequest::query()
            ->active()
            ->where('isSaved', true)
            ->whereNull('batchno')
            ->whereRaw('NOT '.StockRequest::ALL_BATCHED_SQL)
            ->whereRaw(StockRequest::HAS_ISSUED_SQL)
            ->orderByDesc('id')
            ->get(['transaction_no', 'dept'])
            ->map(fn ($row) => ['transaction_no' => $row->transaction_no, 'dept' => $row->dept]);
    }

    /**
     * @param  list<string>  $transactions
     * @return Collection<int, RequestedItem>
     */
    private function batchableItems(array $transactions): Collection
    {
        if ($transactions === []) {
            return collect();
        }

        return RequestedItem::query()
            ->withIssued()
            ->live()
            ->whereIn('requested_items.transaction_no', $transactions)
            ->whereNull('requested_items.batchno')
            ->whereRaw(RequestedItem::ISSUED_QTY_SQL.' <> 0')
            ->orderByDesc('requested_items.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function selectedTransactions(Request $request): array
    {
        $validated = $request->validate(['transactions' => ['nullable', 'array'], 'transactions.*' => ['string', 'max:50']]);

        return array_values(array_filter($validated['transactions'] ?? []));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function batchItems(Batch $batch): Collection
    {
        return RequestedItem::query()->withIssued()
            ->where('requested_items.batchno', $batch->batchno)
            ->orderByDesc('requested_items.id')
            ->get()
            ->map(fn (RequestedItem $item) => $this->itemRow($item));
    }

    /**
     * @return array<string, mixed>
     */
    private function batchFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(self::BATCH_SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
    }

    private function batchQuery(array $filters): Builder
    {
        $query = Batch::query()->live();

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }
        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($query) => $query->where('description', 'like', $like)->orWhere('batchno', 'like', $like));
        }

        $sort = $filters['sort'] ?? 'id';
        $query->orderBy($sort, $filters['direction'] ?? 'desc');
        if ($sort !== 'id') {
            $query->orderByDesc('id');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function batchRow(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'batchno' => $batch->batchno,
            'description' => $batch->description,
            'created_at' => $batch->created_at?->format('Y-m-d h:i A'),
            'created_by' => $batch->created_by,
        ];
    }

    private function itemReport(Request $request, string $report): Response
    {
        $filters = $this->itemFilters($request);

        return Inertia::render('McdRecords/ItemReport', [
            'report' => $report,
            'items' => $this->itemQuery($report, $filters)
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
                ->through(fn (RequestedItem $item) => $this->itemRow($item)),
            'filters' => $filters,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::ITEM_SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
    }

    /**
     * Unbatched: live, unclosed items without a batch. Unserved: live, unclosed items nothing was issued against.
     */
    private function itemQuery(string $report, array $filters): Builder
    {
        $query = RequestedItem::query()
            ->withIssued()
            ->live()
            ->whereNull('requested_items.isClosed')
            ->when($report === 'unbatched', fn ($query) => $query->whereNull('requested_items.batchno'))
            ->when($report === 'unserved', fn ($query) => $query->whereRaw(RequestedItem::ISSUED_QTY_SQL.' = 0'));

        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($query) => $query->where('requested_items.transaction_no', 'like', $like)
                ->orWhere('requested_items.stock_code', 'like', $like)
                ->orWhere('requested_items.description', 'like', $like));
        }

        $sort = $filters['sort'] ?? null;
        $direction = $filters['direction'] ?? 'desc';

        match (true) {
            $sort === 'issued_qty' => $query->orderByRaw(RequestedItem::ISSUED_QTY_SQL.' '.$direction),
            $sort !== null => $query->orderBy("requested_items.{$sort}", $direction),
            default => $query->orderByDesc('requested_items.created_at'),
        };

        return $query->orderByDesc('requested_items.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function productFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::PRODUCT_SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRow(RequestedItem $item): array
    {
        return [
            'id' => $item->id,
            'transaction_no' => $item->transaction_no,
            'stock_code' => $item->stock_code,
            'description' => $item->description,
            'uom' => $item->uom,
            'requested_qty' => (int) $item->requested_qty,
            'issued_qty' => (int) $item->issued_qty,
            'remarks' => $item->remarks,
            'batchno' => $item->batchno,
            'isClosed' => (int) $item->isClosed === 1,
            'isClosed_remarks' => $item->isClosed_remarks,
        ];
    }

    private function ensureLive(Batch $batch): void
    {
        abort_if($batch->deleted_at !== null, HttpResponse::HTTP_NOT_FOUND);
    }

    private function can(Request $request, string $page, string $action): bool
    {
        return $this->accessRights->can($request->user(), $page, $action);
    }
}
