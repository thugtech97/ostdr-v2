<?php

namespace App\Http\Controllers\Concerns;

use App\Models\StockRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The legacy stock request list behaviour shared by the requestor and receiver pages:
 * optional date bounds on the created date, keyword search, whitelisted sorting.
 */
trait FiltersStockRequests
{
    /**
     * @param  list<string>  $sortable
     * @return array<string, mixed>
     */
    protected function listFilters(Request $request, array $sortable): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in($sortable)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);
    }

    protected function applyFilters(Builder $query, array $filters, bool $searchItems): void
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

    protected function cleanTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * @param  iterable<StockRequest>  $rows
     * @param  array<string, callable(StockRequest): mixed>  $columns  header => value
     */
    protected function csvDownload(string $filename, iterable $rows, array $columns): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, array_keys($columns));

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => $value($row), array_values($columns)));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
