<?php

namespace App\Http\Requests\Admin;

use App\Models\Satellite;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SatelliteRequest extends FormRequest
{
    /**
     * Access is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Legacy stores satellite names in upper case; they double as department names.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::upper(Str::squish((string) $this->input('name'))),
            'description' => trim((string) $this->input('description')),
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Satellite|null $satellite */
        $satellite = $this->route('satellite');

        return [
            'name' => ['required', 'string', 'max:190', Rule::unique('satellites', 'name')->ignore($satellite?->id)],
            'description' => ['required', 'string', 'max:190'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'satellite name', 'description' => 'satellite description'];
    }
}
