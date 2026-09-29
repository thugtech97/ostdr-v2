<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Blocks values a spreadsheet would run as a formula when the list is exported (legacy rule).
     */
    private const NO_FORMULA = 'regex:/^[^=+\-@\t\r]/';

    /**
     * Access is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Legacy stores role names in upper case.
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
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:150', self::NO_FORMULA,
                Rule::notIn(['ADMIN']),
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'description' => ['required', 'string', 'max:190', self::NO_FORMULA],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The role name cannot start with =, +, -, or @.',
            'description.regex' => 'The description cannot start with =, +, -, or @.',
            'name.not_in' => 'That role name is reserved.',
        ];
    }
}
