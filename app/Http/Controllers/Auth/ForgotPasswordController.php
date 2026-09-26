<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    /**
     * Display the forgot password view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a password assistance request to IT. As in the legacy app,
     * passwords are reset by IT rather than through a self-service link.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100'],
        ], [
            'username.required' => 'Username required!',
        ]);

        $itEmail = config('app.it_email');

        if (! $itEmail) {
            throw ValidationException::withMessages([
                'username' => 'Password requests are not available right now. Please contact IT directly.',
            ]);
        }

        Mail::to($itEmail)->send(new ForgotPasswordRequest($validated['username']));

        return back()->with('status', 'Request Email Sent');
    }
}
