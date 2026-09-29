<?php

namespace Tests\Feature\Admin;

use App\Models\Audit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private function manager(array $actions = ['view', 'create', 'edit']): User
    {
        $user = User::factory()->create();
        $this->grantUser($user, 'Roles', $actions);

        return $user;
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/roles/dashboard')->assertForbidden();
    }

    public function test_roles_are_listed_without_admin_and_with_user_counts(): void
    {
        Role::factory()->create(['name' => 'ADMIN']);
        $requestor = Role::factory()->create(['name' => 'REQUESTOR']);
        User::factory()->count(2)->create(['role_id' => $requestor->id, 'role' => 'REQUESTOR']);

        $this->actingAs($this->manager())
            ->get('/roles/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Roles/Index')
                ->has('roles', 1)
                ->where('roles.0.name', 'REQUESTOR')
                ->where('roles.0.users_count', 2));
    }

    public function test_a_role_can_be_created(): void
    {
        $this->actingAs($this->manager())
            ->post('/roles', ['name' => ' approver ', 'description' => 'Approves Stock Requests', 'active' => true])
            ->assertRedirect(route('roles.index', absolute: false));

        $role = Role::where('name', 'APPROVER')->sole();
        $this->assertTrue($role->active);
        $this->assertSame(1, Audit::where('auditable_type', Role::class)->where('event', 'created')->count());
    }

    public function test_role_input_is_validated(): void
    {
        Role::factory()->create(['name' => 'RECEIVER']);
        $manager = $this->manager();

        $this->actingAs($manager)->post('/roles', ['name' => 'receiver', 'description' => 'Dup'])->assertSessionHasErrors('name');
        $this->actingAs($manager)->post('/roles', ['name' => 'admin', 'description' => 'Nope'])->assertSessionHasErrors('name');
        $this->actingAs($manager)->post('/roles', ['name' => '=CMD()', 'description' => '@SUM(A1)'])->assertSessionHasErrors(['name', 'description']);
        $this->actingAs($manager)->post('/roles', ['name' => '', 'description' => ''])->assertSessionHasErrors(['name', 'description']);
    }

    public function test_renaming_a_role_updates_its_users(): void
    {
        $role = Role::factory()->create(['name' => 'RECEIVER']);
        $user = User::factory()->create(['role_id' => $role->id, 'role' => 'RECEIVER']);

        $this->actingAs($this->manager())
            ->put("/roles/{$role->id}", ['name' => 'RECEIVING CLERK', 'description' => $role->description, 'active' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame('RECEIVING CLERK', $role->refresh()->name);
        $this->assertSame('RECEIVING CLERK', $user->refresh()->role);

        $audit = Audit::where('auditable_type', Role::class)->where('event', 'updated')->sole();
        $this->assertSame(['name' => 'RECEIVER'], $audit->old_values);
        $this->assertSame(['name' => 'RECEIVING CLERK'], $audit->new_values);
    }

    public function test_a_role_can_be_deactivated(): void
    {
        $role = Role::factory()->create();

        $this->actingAs($this->manager())
            ->put("/roles/{$role->id}", ['name' => $role->name, 'description' => $role->description, 'active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($role->refresh()->active);
    }

    public function test_editing_requires_edit_permission_and_admin_role_is_protected(): void
    {
        $role = Role::factory()->create();
        $admin = Role::factory()->create(['name' => 'ADMIN']);

        $this->actingAs($this->manager(['view']))->get("/roles/edit/{$role->id}")->assertForbidden();
        $this->actingAs($this->manager())->get("/roles/edit/{$admin->id}")->assertNotFound();
    }
}
