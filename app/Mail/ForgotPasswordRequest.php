<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $username)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Forgot Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.forgot-password-request',
        );
    }
}
