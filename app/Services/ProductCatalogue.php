<?php

namespace App\Services;

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
            // SQL Server LIKE escapes wildcards by wrapping them in brackets.
            ->where($column, 'like', str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term).'%')
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

    protected function published(): Builder
    {
        return DB::connection('catalogue')
            ->table('products')
            ->where('status', 'Published')
            ->whereNotNull('code')
            ->whereRaw('ISNUMERIC(code) = 1');
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
