<?php

namespace Tests\Feature\Auth;

use App\Mail\ForgotPasswordRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/auth/forgot_password');

        $response->assertStatus(200);
    }

    public function test_request_is_emailed_to_it(): void
    {
        Mail::fake();
        config(['app.it_email' => 'it@example.com']);

        $response = $this->post('/auth/forgot_password', ['username' => 'jdoe']);

        $response->assertSessionHas('status', 'Request Email Sent');
        Mail::assertSent(ForgotPasswordRequest::class, function (ForgotPasswordRequest $mail) {
            return $mail->hasTo('it@example.com') && $mail->username === 'jdoe';
        });
    }

    public function test_username_is_required(): void
    {
        Mail::fake();
        config(['app.it_email' => 'it@example.com']);

        $response = $this->post('/auth/forgot_password', []);

        $response->assertSessionHasErrors(['username' => 'Username required!']);
        Mail::assertNothingSent();
    }

    public function test_request_fails_gracefully_when_it_email_is_not_configured(): void
    {
        Mail::fake();
        config(['app.it_email' => null]);

        $response = $this->post('/auth/forgot_password', ['username' => 'jdoe']);

        $response->assertSessionHasErrors('username');
        Mail::assertNothingSent();
    }
}
