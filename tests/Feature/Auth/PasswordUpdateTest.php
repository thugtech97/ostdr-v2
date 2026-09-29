<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/auth/change_password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/auth/change_password')
            ->put('/auth/change_password', [
                'current_password' => 'password',
                'password' => 'New-Passw0rd!',
                'password_confirmation' => 'New-Passw0rd!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/auth/change_password');

        $this->assertTrue(Hash::check('New-Passw0rd!', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/auth/change_password')
            ->put('/auth/change_password', [
                'current_password' => 'wrong-password',
                'password' => 'New-Passw0rd!',
                'password_confirmation' => 'New-Passw0rd!',
            ]);

        $response
            ->assertSessionHasErrors(['current_password' => 'Incorrect Current Password.'])
            ->assertRedirect('/auth/change_password');
    }

    public function test_new_password_must_meet_the_legacy_strength_rules(): void
    {
        $user = User::factory()->create();

        foreach (['short1!', 'alllowercase1!', 'NoNumbers!!', 'NoSymbols123'] as $weak) {
            $response = $this
                ->actingAs($user)
                ->put('/auth/change_password', [
                    'current_password' => 'password',
                    'password' => $weak,
                    'password_confirmation' => $weak,
                ]);

            $response->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_guests_can_not_change_passwords(): void
    {
        $response = $this->get('/auth/change_password');

        $response->assertRedirect(route('login', absolute: false));
    }
}
