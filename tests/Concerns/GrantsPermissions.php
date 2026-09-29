<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait GrantsPermissions
{
    /**
     * Get (or create) a legacy permission page and return its id.
     */
    protected function permissionId(string $page, bool $active = true): int
    {
        $existing = DB::table('permissions')->where('description', $page)->value('id');

        return $existing ?? DB::table('permissions')->insertGetId([
            'description' => $page,
            'module_type' => 'Maintenance Module',
            'active' => $active,
        ]);
    }

    /**
     * Give a user their own rows in users_permissions for a page.
     */
    protected function grantUser(User $user, string $page, array $actions = ['view']): void
    {
        $permissionId = $this->permissionId($page);

        foreach ($actions as $action) {
            DB::table('users_permissions')->insert([
                'user_id' => $user->id,
                'permission_id' => $permissionId,
                'module_id' => 1,
                'action' => $action,
            ]);
        }
    }

    /**
     * Give a role rows in roles_permissions for a page.
     */
    protected function grantRole(int $roleId, string $page, array $actions = ['view']): void
    {
        $permissionId = $this->permissionId($page);

        foreach ($actions as $action) {
            DB::table('roles_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'module_id' => 1,
                'action' => $action,
            ]);
        }
    }
}
