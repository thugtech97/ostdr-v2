<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Satellite;
use App\Models\User;
use App\Services\AccessRightService;
use App\Services\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class MaintenancePagesTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private function adminOf(string $page, array $actions = ['view', 'create', 'edit', 'delete']): User
    {
        $user = User::factory()->create();
        $this->grantUser($user, $page, $actions);

        return $user;
    }

    public function test_satellites_list_hides_mcd_and_can_be_maintained(): void
    {
        Satellite::create(['name' => 'MCD', 'description' => 'parent', 'active' => true]);
        $ict = Satellite::create(['name' => 'ICT', 'description' => 'ICT', 'active' => true]);
        $user = $this->adminOf('Satellites');

        $this->actingAs($user)->get('/satellites/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Satellites/Index')->has('satellites', 1));

        $this->actingAs($user)->post('/satellites', ['name' => ' mill site ', 'description' => 'Mill', 'active' => true])
            ->assertRedirect('/satellites/dashboard');
        $this->assertDatabaseHas('satellites', ['name' => 'MILL SITE', 'active' => true]);

        $this->actingAs($user)->post('/satellites', ['name' => 'ict', 'description' => 'dup'])->assertSessionHasErrors('name');

        $this->actingAs($user)->put("/satellites/{$ict->id}", ['name' => 'ICT', 'description' => 'Info Tech', 'active' => false])
            ->assertRedirect('/satellites/dashboard');
        $this->assertFalse($ict->refresh()->active);
    }

    public function test_permissions_can_be_maintained(): void
    {
        DB::table('modules')->insert([['description' => 'Receiving Module'], ['description' => 'Maintenance Module']]);
        $user = $this->adminOf('Permissions');

        $this->actingAs($user)->post('/permissions', ['module_type' => 'Receiving Module', 'description' => 'Manage Receiving', 'active' => true])
            ->assertRedirect('/permissions/dashboard');

        $permission = Permission::where('description', 'Manage Receiving')->sole();

        $this->actingAs($user)->post('/permissions', ['module_type' => 'Nope', 'description' => 'Manage Receiving'])
            ->assertSessionHasErrors(['module_type', 'description']);

        $this->actingAs($user)->put("/permissions/{$permission->id}", ['module_type' => 'Receiving Module', 'description' => 'Manage Receiving', 'active' => false])
            ->assertRedirect('/permissions/dashboard');
        $this->assertFalse($permission->refresh()->active);
    }

    public function test_user_access_rights_replace_the_users_rows(): void
    {
        DB::table('modules')->insert(['description' => 'Receiving Module']);
        $manage = Permission::create(['module_type' => 'Receiving Module', 'description' => 'Manage Receiving', 'active' => true]);
        $completed = Permission::create(['module_type' => 'Receiving Module', 'description' => 'Completed Request', 'active' => true]);
        $admin = $this->adminOf('User Access Rights', ['view', 'edit']);
        $target = User::factory()->create();
        $this->grantUser($target, 'Completed Request', ['view']);

        $this->actingAs($admin)->get("/accessrights/user?user_id={$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AccessRights/Grid')
                ->where('granted', ["{$completed->id}:view"])
                ->has('modules', 1)
                ->has('modules.0.permissions', 2));

        $this->actingAs($admin)->put("/accessrights/user/{$target->id}", ['grants' => ["{$manage->id}:view", "{$manage->id}:edit"]])
            ->assertSessionHas('success');

        $rows = DB::table('users_permissions')->where('user_id', $target->id)->get();
        $this->assertCount(2, $rows);
        $this->assertSame($manage->id, (int) $rows[0]->permission_id);
        $this->assertSame((int) DB::table('modules')->value('id'), (int) $rows[0]->module_id);

        $fresh = new AccessRightService;
        $this->assertTrue($fresh->can($target->refresh(), 'Manage Receiving', 'edit'));
        $this->assertFalse($fresh->can($target, 'Completed Request', 'view'));
    }

    public function test_access_rights_reject_unknown_actions_and_need_edit(): void
    {
        $viewer = $this->adminOf('Role Access Rights', ['view']);
        $role = Role::factory()->create(['name' => 'RECEIVER']);

        $this->actingAs($viewer)->put("/accessrights/role/{$role->id}", ['grants' => []])->assertForbidden();

        $editor = $this->adminOf('Role Access Rights', ['view', 'edit']);
        $this->actingAs($editor)->put("/accessrights/role/{$role->id}", ['grants' => ['1:approve']])->assertSessionHasErrors('grants.0');
    }

    public function test_shutdowns_can_be_scheduled_and_are_announced(): void
    {
        $user = $this->adminOf('Application Maintenance');

        $this->actingAs($user)->post('/applications', [
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '18:30',
            'reason' => 'Server patching',
        ])->assertRedirect('/applications/dashboard');

        $schedule = Application::sole();
        $this->assertNotNull($schedule->posted_date);

        $this->actingAs(User::factory()->create())->get('/reports/stockRequests')
            ->assertInertia(fn ($page) => $page->where('shutdown.reason', 'Server patching'));

        $this->actingAs($user)->delete("/applications/{$schedule->id}")->assertSessionHas('success');
        $this->assertNotNull($schedule->refresh()->deleted_at);
        $this->assertNull(Application::upcoming());
    }

    public function test_stopping_and_starting_the_app(): void
    {
        $this->mock(MaintenanceMode::class, function ($mock) {
            $mock->shouldReceive('down')->once()->andReturn('bypass-secret');
            $mock->shouldReceive('up')->once();
        });
        $user = $this->adminOf('Application Maintenance');

        $this->actingAs($user)->post('/applications/systemDown', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('bypass-secret'));

        $this->actingAs($user)->post('/applications/systemUp')->assertSessionHas('success');
    }

    public function test_due_schedules_take_the_app_down(): void
    {
        $this->mock(MaintenanceMode::class, function ($mock) {
            $mock->shouldReceive('isDown')->andReturn(false);
            $mock->shouldReceive('down')->once();
        });

        Application::create(['scheduled_date' => now()->toDateString(), 'scheduled_time' => now()->subMinute()->format('H:i:s'), 'reason' => 'due']);
        Application::create(['scheduled_date' => now()->toDateString(), 'scheduled_time' => now()->subHour()->format('H:i:s'), 'reason' => 'long past']);

        $this->artisan('ostr:scheduled-shutdown')->assertSuccessful();
    }

    public function test_pages_need_their_permission(): void
    {
        $nobody = User::factory()->create();

        foreach (['/satellites/dashboard', '/permissions/dashboard', '/accessrights/user', '/accessrights/role', '/applications/dashboard', '/reports/audit-logs', '/reports/error-logs'] as $url) {
            $this->actingAs($nobody)->get($url)->assertForbidden();
        }
    }
}
