<?php

namespace App\Http\Requests;

use App\Models\Satellite;
use App\Models\StockRequest;
use App\Services\ProductCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockRequestForm extends FormRequest
{
    /**
     * Legacy limit on items per transaction.
     */
    public const MAX_ITEMS = 10;

    /**
     * Access is enforced by the route's permission middleware and the controller's ownership checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Where a request can transfer stock from: the MCD warehouses and active satellites,
     * never the requesting department itself (legacy rule).
     *
     * @return list<string>
     */
    public static function originOptions(?string $dept): array
    {
        $own = Str::lower(trim((string) $dept));

        return collect(['MCD MINE', 'MCD MILL'])
            ->merge(Satellite::query()->where('active', true)->where('name', '<>', 'MCD')->orderBy('name')->pluck('name'))
            ->unique()
            ->reject(fn ($origin) => Str::lower(trim($origin)) === $own)
            ->values()
            ->all();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', Rule::in(self::originOptions($this->user()->dept))],
            'date_needed' => ['required', 'date'],
            'requestor' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.stock_code' => ['required', 'string', 'max:50', 'distinct'],
            'items.*.requested_qty' => ['required', 'integer', 'min:1'],
            'items.*.remarks' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origin.required' => 'Origin is required.',
            'origin.in' => 'Please pick where the stock transfers from.',
            'date_needed.required' => 'The Date Needed is required.',
            'remarks.max' => 'The Remarks must not be greater than 255 characters.',
            'items.required' => 'Please add atleast 1 item!',
            'items.min' => 'Please add atleast 1 item!',
            'items.max' => 'Reached the maximum limit of '.self::MAX_ITEMS.' items per transaction!',
            'items.*.stock_code.distinct' => 'Stock Code already exists in the item list!',
            'items.*.requested_qty.min' => 'Requested Qty. is required!',
            'items.*.remarks.required' => 'Item remarks is required!',
        ];
    }

    /**
     * Item descriptions and units come from the catalogue, not the browser.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $known = $this->knownProducts();

                foreach ($this->input('items', []) as $index => $item) {
                    if (! $known->has($item['stock_code'])) {
                        $validator->errors()->add("items.{$index}.stock_code", "Item {$item['stock_code']} was not found in the catalogue.");
                    }
                }
            },
        ];
    }

    /**
     * Items ready to insert, with description/UoM resolved from the catalogue.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $known = $this->knownProducts();

        return collect($this->validated('items'))
            ->map(fn ($item) => [
                'stock_code' => $item['stock_code'],
                'description' => $known[$item['stock_code']]['description'],
                'uom' => $known[$item['stock_code']]['uom'],
                'requested_qty' => (int) $item['requested_qty'],
                'remarks' => $item['remarks'],
            ])
            ->all();
    }

    /**
     * Catalogue products for the submitted codes. When editing, items already on the request
     * stay valid even if the catalogue has since unpublished them.
     *
     * @return \Illuminate\Support\Collection<string, array{description: ?string, uom: ?string}>
     */
    private function knownProducts()
    {
        return once(function () {
            $codes = collect($this->input('items', []))->pluck('stock_code')->filter()->unique()->values()->all();

            /** @var StockRequest|null $existing */
            $existing = $this->route('stockRequest');

            $fromRequest = $existing
                ? $existing->items()->whereIn('stock_code', $codes)->get()
                    ->mapWithKeys(fn ($item) => [$item->stock_code => ['description' => $item->description, 'uom' => $item->uom]])
                : collect();

            $fromCatalogue = app(ProductCatalogue::class)->findByCodes($codes)
                ->map(fn ($product) => ['description' => $product->name, 'uom' => $product->uom]);

            // replace(), not merge(): numeric stock codes become integer keys, which merge() would renumber.
            return $fromRequest->replace($fromCatalogue);
        });
    }
}
