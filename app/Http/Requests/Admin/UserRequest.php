<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Access is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Legacy stores usernames in upper case.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::upper(trim((string) $this->input('username'))),
            'name' => Str::squish((string) $this->input('name')),
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required', 'string', 'max:100', 'alpha_num',
                Rule::notIn(['ADMIN']),
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'dept' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => [
                'required', 'integer',
                Rule::exists('roles', 'id')->where(function ($query) use ($user) {
                    // A user may keep an inactive role they already have; new assignments must be active.
                    $query->where('name', '<>', 'ADMIN')
                        ->where(fn ($q) => $q->where('active', true)->orWhere('id', $user?->role_id ?? 0));
                }),
            ],
            'password' => [
                $user ? 'nullable' : 'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.not_in' => 'That username is reserved.',
            'username.alpha_num' => 'The username may only contain letters and numbers.',
            'role_id.required' => 'Please select a role.',
            'role_id.exists' => 'Please select a valid role.',
            'dept.required' => 'Please select a department.',
        ];
    }

    /**
     * The validated attributes ready to save, with the role name denormalized as legacy expects.
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        $data = $this->safe()->only(['name', 'username', 'dept', 'email', 'role_id']);
        $data['role'] = Role::findOrFail($data['role_id'])->name;

        if ($this->filled('password')) {
            $data['password'] = $this->input('password');
        }

        return $data;
    }
}
