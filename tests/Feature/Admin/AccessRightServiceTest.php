<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\AccessRightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class AccessRightServiceTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    public function test_admin_can_do_everything(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(app(AccessRightService::class)->can($admin, 'Users Maintenance', 'delete'));
    }

    public function test_users_without_their_own_rights_fall_back_to_their_role(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'role' => $role->name]);
        $this->grantRole($role->id, 'Users Maintenance', ['view', 'edit']);

        $service = app(AccessRightService::class);

        $this->assertTrue($service->can($user, 'Users Maintenance', 'view'));
        $this->assertTrue($service->can($user, 'Users Maintenance', 'edit'));
        $this->assertFalse($service->can($user, 'Users Maintenance', 'create'));
        $this->assertFalse($service->can($user, 'Roles', 'view'));
    }

    public function test_users_own_rights_replace_their_role_rights_entirely(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'role' => $role->name]);
        $this->grantRole($role->id, 'Users Maintenance', ['view', 'edit']);
        $this->grantUser($user, 'Roles', ['view']);

        $service = app(AccessRightService::class);

        $this->assertTrue($service->can($user, 'Roles', 'view'));
        $this->assertFalse($service->can($user, 'Users Maintenance', 'view'));
    }

    public function test_inactive_permission_pages_grant_nothing(): void
    {
        $user = User::factory()->create();
        $this->grantUser($user, 'Users Maintenance', ['view']);
        DB::table('permissions')->where('description', 'Users Maintenance')->update(['active' => false]);

        $this->assertFalse(app(AccessRightService::class)->can($user, 'Users Maintenance', 'view'));
    }

    public function test_permissions_are_shared_with_the_frontend(): void
    {
        $user = User::factory()->create();
        $this->grantUser($user, 'Roles', ['view', 'create']);

        $this->actingAs($user)
            ->get('/stockrequests/main-dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.isAdmin', false)
                ->where('auth.permissions.Roles', ['view', 'create']));
    }
}
