<?php

namespace Tests\Feature\Auth;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_authenticated_users_are_redirected_away_from_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_with_their_username(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_is_audited_in_the_legacy_format(): void
    {
        $user = User::factory()->create();

        $this->post('/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $audit = Audit::where('event', 'Login')->sole();
        $this->assertSame('Login', $audit->event);
        $this->assertSame('App\User', $audit->user_type);
        $this->assertSame($user->id, (int) $audit->user_id);
        $this->assertSame(['action' => 'Login User : '.$user->username], $audit->new_values);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/auth/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['username' => 'Invalid Credential or User is Inactive!']);
        $this->assertSame(0, Audit::whereIn('event', ['Login', 'Admin Login'])->count());
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->post('/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['username' => 'Invalid Credential or User is Inactive!']);
    }

    public function test_username_and_password_are_required(): void
    {
        $response = $this->post('/auth/login', []);

        $response->assertSessionHasErrors([
            'username' => 'Username required!',
            'password' => 'Password required!',
        ]);
    }

    public function test_login_is_throttled_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/auth/login', ['username' => $user->username, 'password' => 'wrong']);
        }

        $response = $this->post('/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/auth/adminlogin');

        $response->assertStatus(200);
    }

    public function test_admins_can_authenticate_through_the_admin_login(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/auth/adminlogin', [
            'username' => $admin->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame(1, Audit::where('event', 'Admin Login')->count());
    }

    public function test_non_admins_are_rejected_by_the_admin_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/auth/adminlogin', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['username' => 'This page is for Admin only!']);
        $this->assertSame(0, Audit::whereIn('event', ['Login', 'Admin Login'])->count());
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
        $this->assertSame(1, Audit::where('event', 'Logout')->count());
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login', absolute: false));
    }
}
