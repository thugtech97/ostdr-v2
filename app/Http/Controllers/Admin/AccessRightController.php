<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessRightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The legacy access-right grids: per module, each active permission page with
 * view / create / edit / delete / print / upload checkboxes. Saving replaces every row
 * the user (or role) had. A user with no rows of their own falls back to the role's.
 */
class AccessRightController extends Controller
{
    public const PAGE_USER = 'User Access Rights';

    public const PAGE_ROLE = 'Role Access Rights';

    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'print', 'upload'];

    public function user(Request $request, AccessRightService $accessRights): Response
    {
        $userId = $request->integer('user_id') ?: null;

        return Inertia::render('AccessRights/Grid', [
            'kind' => 'user',
            'subjects' => User::query()->manageable()->where('isActive', 1)->orderBy('username')
                ->get(['id', 'username', 'name', 'role'])
                ->map(fn (User $user) => ['id' => $user->id, 'label' => $user->username, 'hint' => null]),
            'selected' => $userId,
            'modules' => $this->modules(),
            'granted' => $userId ? $this->granted('users_permissions', 'user_id', $userId) : [],
            'canSave' => $accessRights->can($request->user(), self::PAGE_USER, 'edit'),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_if(strtoupper((string) $user->username) === 'ADMIN', 404);

        $this->replace('users_permissions', 'user_id', $user->id, $this->validatedGrants($request));

        return back()->with('success', 'User Access Right has been saved successfully!');
    }

    public function role(Request $request, AccessRightService $accessRights): Response
    {
        $roleId = $request->integer('role_id') ?: null;

        return Inertia::render('AccessRights/Grid', [
            'kind' => 'role',
            'subjects' => Role::query()->assignable()->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->map(fn (Role $role) => ['id' => $role->id, 'label' => $role->description ?: $role->name, 'hint' => null]),
            'selected' => $roleId,
            'modules' => $this->modules(),
            'granted' => $roleId ? $this->granted('roles_permissions', 'role_id', $roleId) : [],
            'canSave' => $accessRights->can($request->user(), self::PAGE_ROLE, 'edit'),
        ]);
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_if(strtoupper((string) $role->name) === 'ADMIN', 404);

        $this->replace('roles_permissions', 'role_id', $role->id, $this->validatedGrants($request));

        return back()->with('success', 'Role Access Right has been saved successfully!');
    }

    /**
     * Modules with their active permission pages, in legacy order.
     *
     * @return Collection<int, array{id: int, description: string, permissions: list<array{id: int, description: string}>}>
     */
    private function modules(): Collection
    {
        $permissions = DB::table('permissions')->where('active', true)->orderBy('description')->get(['id', 'description', 'module_type'])
            ->groupBy('module_type');

        return DB::table('modules')->orderBy('description')->get(['id', 'description'])
            ->map(fn ($module) => [
                'id' => (int) $module->id,
                'description' => $module->description,
                'permissions' => ($permissions[$module->description] ?? collect())
                    ->map(fn ($permission) => ['id' => (int) $permission->id, 'description' => $permission->description])
                    ->values()->all(),
            ])
            ->filter(fn ($module) => $module['permissions'] !== [])
            ->values();
    }

    /**
     * @return list<string> "permissionId:action"
     */
    private function granted(string $table, string $column, int $id): array
    {
        return DB::table($table)->where($column, $id)->get(['permission_id', 'action'])
            ->map(fn ($row) => "{$row->permission_id}:{$row->action}")
            ->unique()->values()->all();
    }

    /**
     * @return list<array{permission_id: int, action: string}>
     */
    private function validatedGrants(Request $request): array
    {
        $validated = $request->validate([
            'grants' => ['present', 'array'],
            'grants.*' => ['string', 'regex:/^\d+:('.implode('|', self::ACTIONS).')$/'],
        ]);

        return collect($validated['grants'])->unique()->map(function (string $grant) {
            [$permissionId, $action] = explode(':', $grant);

            return ['permission_id' => (int) $permissionId, 'action' => $action];
        })->values()->all();
    }

    /**
     * @param  list<array{permission_id: int, action: string}>  $grants
     */
    private function replace(string $table, string $column, int $id, array $grants): void
    {
        // Legacy also stores the permission's module id on each row.
        $modules = DB::table('permissions')
            ->join('modules', 'modules.description', '=', 'permissions.module_type')
            ->whereIn('permissions.id', array_column($grants, 'permission_id'))
            ->pluck('modules.id', 'permissions.id');

        $rows = collect($grants)
            ->filter(fn ($grant) => isset($modules[$grant['permission_id']]))
            ->map(fn ($grant) => [
                $column => $id,
                'permission_id' => $grant['permission_id'],
                'module_id' => (int) $modules[$grant['permission_id']],
                'action' => $grant['action'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::transaction(function () use ($table, $column, $id, $rows) {
            DB::table($table)->where($column, $id)->delete();

            // SQL Server caps a statement at 2100 parameters.
            $rows->chunk(200)->each(fn ($chunk) => DB::table($table)->insert($chunk->values()->all()));
        });
    }
}
