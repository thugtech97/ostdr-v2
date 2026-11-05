<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Published products from PMC-CATALOGUE, the list stock request items are picked from.
 *
 * The catalogue holds duplicate rows for some codes and pads names with trailing spaces,
 * so results are de-duplicated and trimmed.
 */
class ProductCatalogue
{
    /**
     * Legacy matches the start of the stock code or the item name.
     *
     * @return list<array{code: string, name: ?string, uom: ?string, oem: ?string}>
     */
    public function search(string $field, string $term, int $limit = 20): array
    {
        $column = $field === 'name' ? 'name' : 'code';

        return $this->published()
            ->where($column, 'like', $this->escapeLike($term).'%')
            ->distinct()
            ->orderBy($column)
            ->limit($limit * 3)
            ->get(['code', 'name', 'uom', 'oem'])
            ->map(fn ($row) => $this->clean($row))
            ->unique('code')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $codes
     * @return Collection<string, object> keyed by code
     */
    public function findByCodes(array $codes): Collection
    {
        if ($codes === []) {
            return collect();
        }

        return $this->published()
            ->whereIn('code', $codes)
            ->get(['code', 'name', 'uom', 'oem'])
            ->map(fn ($row) => (object) $this->clean($row))
            ->keyBy('code');
    }

    /**
     * The MCD Records "Catalogue Items" list.
     *
     * @param  array{search?: ?string, sort?: ?string, direction?: ?string, per_page?: ?int}  $filters
     */
    public function browse(array $filters): LengthAwarePaginator
    {
        $query = $this->published();

        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($query) => $query->where('code', 'like', $like)->orWhere('name', 'like', $like)
                ->orWhere('uom', 'like', $like)->orWhere('oem', 'like', $like));
        }

        $sort = $filters['sort'] ?? 'code';
        $query->orderBy($sort, $filters['direction'] ?? 'asc');
        if ($sort !== 'id') {
            $query->orderBy('id');
        }

        return $query->select(['id', 'code', 'name', 'uom', 'oem'])
            ->paginate($filters['per_page'] ?? 10)
            ->through(fn ($row) => ['id' => (string) $row->id, ...$this->clean($row)]);
    }

    /**
     * Update or add catalogue items from the MCD stock list, the legacy "Update Items" upload:
     * existing codes get the new description/OEM/UoM, unknown codes are published as new items.
     *
     * @param  list<array{code: string, description: ?string, oem: ?string, uom: ?string}>  $rows
     * @return array{updated: int, created: int}
     */
    public function importStockList(array $rows, int $userId): array
    {
        $counts = ['updated' => 0, 'created' => 0];

        DB::connection('catalogue')->transaction(function () use ($rows, $userId, &$counts) {
            $products = DB::connection('catalogue')->table('products');

            foreach ($rows as $row) {
                $values = [
                    'description' => $row['description'],
                    'oem' => $row['oem'],
                    'uom' => $row['uom'],
                    'name' => $row['description'],
                ];

                if ((clone $products)->where('code', $row['code'])->exists()) {
                    (clone $products)->where('code', $row['code'])->update([...$values, 'updated_at' => now()]);
                    $counts['updated']++;
                } else {
                    (clone $products)->insert([
                        ...$values,
                        // Both columns are NOT NULL in the catalogue.
                        'name' => $row['description'] ?: $row['code'],
                        'uom' => $row['uom'] ?: 'PC',
                        'code' => $row['code'],
                        'category_id' => 29,
                        'slug' => 'new-product',
                        'status' => 'PUBLISHED',
                        'created_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $counts['created']++;
                }
            }
        });

        return $counts;
    }

    protected function published(): Builder
    {
        return DB::connection('catalogue')
            ->table('products')
            ->where('status', 'Published')
            ->whereNotNull('code')
            ->whereRaw($this->onSqlServer() ? 'ISNUMERIC(code) = 1' : "TRIM(code) REGEXP '^[0-9]+$'");
    }

    /**
     * SQL Server LIKE escapes wildcards by wrapping them in brackets; MySQL uses a backslash.
     */
    private function escapeLike(string $term): string
    {
        return $this->onSqlServer()
            ? str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term)
            : str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    private function onSqlServer(): bool
    {
        return DB::connection('catalogue')->getDriverName() === 'sqlsrv';
    }

    /**
     * @return array{code: string, name: ?string, uom: ?string, oem: ?string}
     */
    private function clean(object $row): array
    {
        return [
            'code' => trim((string) $row->code),
            'name' => $row->name !== null ? trim($row->name) : null,
            'uom' => $row->uom !== null ? trim($row->uom) : null,
            'oem' => $row->oem !== null ? trim($row->oem) : null,
        ];
    }
}
