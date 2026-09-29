<?php

namespace Tests\Feature\Admin;

use App\Models\Audit;
use App\Models\Role;
use App\Models\User;
use App\Services\HrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use GrantsPermissions, RefreshDatabase;

    private const PAGE = 'Users Maintenance';

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->create(['name' => 'REQUESTOR']);
    }

    private function manager(array $actions = ['view', 'create', 'edit']): User
    {
        $user = User::factory()->create();
        $this->grantUser($user, self::PAGE, $actions);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Juan Dela Cruz',
            'username' => 'jdcruz',
            'dept' => 'MCD MINE',
            'email' => 'jdcruz@example.com',
            'role_id' => $this->role->id,
            'password' => 'Temp-Passw0rd!',
            'password_confirmation' => 'Temp-Passw0rd!',
            ...$overrides,
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/users/dashboard')->assertRedirect(route('login', absolute: false));
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/users/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/users/create')->assertForbidden();
        $this->actingAs($user)->post('/users', $this->validPayload())->assertForbidden();
    }

    public function test_view_only_users_can_list_but_not_create(): void
    {
        $viewer = $this->manager(['view']);

        $this->actingAs($viewer)->get('/users/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.create', false)->where('can.edit', false));
        $this->actingAs($viewer)->get('/users/create')->assertForbidden();
    }

    public function test_list_hides_the_built_in_admin_and_supports_search(): void
    {
        User::factory()->admin()->create(['username' => 'ADMIN']);
        User::factory()->create(['name' => 'Maria Santos', 'username' => 'MSANTOS']);
        User::factory()->create(['name' => 'Pedro Reyes', 'username' => 'PREYES']);

        $this->actingAs($this->manager())
            ->get('/users/dashboard?search=santos')
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->where('users.total', 1)
                ->where('users.data.0.username', 'MSANTOS'));

        $this->actingAs($this->manager())
            ->get('/users/dashboard?per_page=50')
            ->assertInertia(fn ($page) => $page->where(
                'users.data',
                fn ($users) => collect($users)->pluck('username')->doesntContain('ADMIN'),
            ));
    }

    public function test_invalid_sort_columns_are_rejected(): void
    {
        $this->actingAs($this->manager())
            ->get('/users/dashboard?sort=password')
            ->assertSessionHasErrors('sort');
    }

    public function test_a_user_can_be_created(): void
    {
        $response = $this->actingAs($this->manager())->post('/users', $this->validPayload());

        $response->assertRedirect(route('users.index', absolute: false))->assertSessionHas('success');

        $user = User::where('username', 'JDCRUZ')->sole();
        $this->assertSame('REQUESTOR', $user->role);
        $this->assertSame($this->role->id, (int) $user->role_id);
        $this->assertSame(1, $user->isActive);
        $this->assertTrue(Hash::check('Temp-Passw0rd!', $user->password));

        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $user->id)->where('event', 'created')->sole();
        $this->assertSame('JDCRUZ', $audit->new_values['username']);
        $this->assertArrayNotHasKey('password', $audit->new_values);
    }

    public function test_create_validates_input(): void
    {
        User::factory()->create(['username' => 'JDCRUZ']);
        $manager = $this->manager();

        $this->actingAs($manager)->post('/users', $this->validPayload())->assertSessionHasErrors('username');
        $this->actingAs($manager)->post('/users', $this->validPayload(['username' => 'admin']))->assertSessionHasErrors('username');
        $this->actingAs($manager)->post('/users', $this->validPayload(['username' => 'new user', 'password' => 'weak', 'password_confirmation' => 'weak']))
            ->assertSessionHasErrors(['username', 'password']);
        $this->actingAs($manager)->post('/users', $this->validPayload(['username' => 'NEWUSER', 'password' => null, 'password_confirmation' => null]))
            ->assertSessionHasErrors('password');
    }

    public function test_inactive_and_admin_roles_can_not_be_assigned(): void
    {
        $inactive = Role::factory()->inactive()->create();
        $admin = Role::factory()->create(['name' => 'ADMIN']);
        $manager = $this->manager();

        $this->actingAs($manager)->post('/users', $this->validPayload(['role_id' => $inactive->id]))->assertSessionHasErrors('role_id');
        $this->actingAs($manager)->post('/users', $this->validPayload(['role_id' => $admin->id]))->assertSessionHasErrors('role_id');
    }

    public function test_a_user_can_be_updated_without_changing_their_password(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;
        $newRole = Role::factory()->create(['name' => 'RECEIVER']);

        $this->actingAs($this->manager())
            ->put("/users/{$user->id}", $this->validPayload([
                'username' => $user->username,
                'role_id' => $newRole->id,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index', absolute: false));

        $user->refresh();
        $this->assertSame('RECEIVER', $user->role);
        $this->assertSame('Juan Dela Cruz', $user->name);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_an_admin_can_reset_a_users_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->manager())
            ->put("/users/{$user->id}", $this->validPayload([
                'username' => $user->username,
                'password' => 'Reset-Passw0rd!',
                'password_confirmation' => 'Reset-Passw0rd!',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Reset-Passw0rd!', $user->refresh()->password));
    }

    public function test_users_can_be_deactivated_and_activated(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->actingAs($manager)->patch("/users/{$user->id}/deactivate")->assertSessionHas('success');
        $this->assertSame(0, $user->refresh()->isActive);

        $this->actingAs($manager)->patch("/users/{$user->id}/activate")->assertSessionHas('success');
        $this->assertSame(1, $user->refresh()->isActive);
    }

    public function test_status_changes_require_edit_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->manager(['view']))->patch("/users/{$user->id}/deactivate")->assertForbidden();
        $this->assertSame(1, $user->refresh()->isActive);
    }

    public function test_users_can_not_deactivate_themselves(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->patch("/users/{$manager->id}/deactivate")->assertSessionHas('error');
        $this->assertSame(1, $manager->refresh()->isActive);
    }

    public function test_the_built_in_admin_account_can_not_be_managed(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'ADMIN']);
        $manager = $this->manager();

        $this->actingAs($manager)->get("/users/edit/{$admin->id}")->assertNotFound();
        $this->actingAs($manager)->patch("/users/{$admin->id}/deactivate")->assertNotFound();
    }

    public function test_employee_lookup_returns_hris_matches(): void
    {
        $this->mock(HrisService::class, fn ($mock) => $mock
            ->shouldReceive('searchEmployees')->with('abad')->once()
            ->andReturn([['emp_id' => 'PMC-A2340', 'name' => 'ABAD JAYNORIEL LOPEZ', 'department' => 'SECURITY', 'username' => 'JLABAD']]));

        $this->actingAs($this->manager())
            ->getJson('/users/employees?search=abad')
            ->assertOk()
            ->assertJsonPath('0.username', 'JLABAD');
    }

    public function test_employee_lookup_reports_when_hris_is_unavailable(): void
    {
        $this->mock(HrisService::class, fn ($mock) => $mock
            ->shouldReceive('searchEmployees')->andThrow(new RuntimeException('connection refused')));

        $this->actingAs($this->manager())
            ->getJson('/users/employees?search=abad')
            ->assertStatus(503)
            ->assertJsonPath('message', 'The HRIS employee list is unavailable right now.');
    }

    public function test_username_suggestion_follows_the_legacy_convention(): void
    {
        $this->assertSame('JLABAD', HrisService::suggestUsername('Jaynoriel', 'Lopez', 'Abad'));
        $this->assertSame('RDELACRUZ', HrisService::suggestUsername('Ricky', '', 'Dela Cruz'));
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
