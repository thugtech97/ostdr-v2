<?php

namespace App\Http\Requests\Admin;

use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
{
    /**
     * Access is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => Str::squish((string) $this->input('description')),
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Permission|null $permission */
        $permission = $this->route('permission');

        return [
            'module_type' => ['required', 'string', Rule::in(DB::table('modules')->pluck('description')->all())],
            'description' => ['required', 'string', 'max:190', Rule::unique('permissions', 'description')->ignore($permission?->id)],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['module_type' => 'module', 'description' => 'permission description'];
    }
}
