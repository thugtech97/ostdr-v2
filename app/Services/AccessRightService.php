<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves what a user may do, using the legacy rules:
 *  - ADMIN can do everything;
 *  - a user with any rows in users_permissions gets exactly those;
 *  - otherwise the user falls back to their role's rows in roles_permissions.
 *
 * Pages are identified by their legacy permission description (e.g. "Users Maintenance"),
 * actions by view / create / edit / delete / print / upload.
 */
class AccessRightService
{
    /** @var array<int, array<string, list<string>>> */
    protected array $resolved = [];

    /**
     * @return array<string, list<string>> page description => allowed actions
     */
    public function permissionsFor(User $user): array
    {
        return $this->resolved[$user->id] ??= $this->load($user);
    }

    public function can(User $user, string $page, string $action = 'view'): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return in_array($action, $this->permissionsFor($user)[$page] ?? [], true);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function load(User $user): array
    {
        $table = DB::table('users_permissions')->where('user_id', $user->id)->exists()
            ? 'users_permissions'
            : 'roles_permissions';

        $query = DB::table($table)
            ->join('permissions', 'permissions.id', '=', "{$table}.permission_id")
            ->where('permissions.active', true)
            ->select('permissions.description', "{$table}.action");

        $table === 'users_permissions'
            ? $query->where('users_permissions.user_id', $user->id)
            : $query->where('roles_permissions.role_id', $user->role_id);

        return $query->get()
            ->groupBy('description')
            ->map(fn ($rows) => $rows->pluck('action')->filter()->unique()->values()->all())
            ->all();
    }
}
