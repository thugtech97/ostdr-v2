<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(protected AuditService $auditService)
    {
    }

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->auditService->create($request, 'Login User : '.$request->user()->username, 'Login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Display the admin login view.
     */
    public function createAdmin(): Response
    {
        return Inertia::render('Auth/Login', [
            'admin' => true,
        ]);
    }

    /**
     * Handle an incoming admin authentication request. Only users with the ADMIN role may pass.
     */
    public function storeAdmin(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if (! $request->user()->isAdmin()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'username' => 'This page is for Admin only!',
            ]);
        }

        $request->session()->regenerate();

        $this->auditService->create($request, 'Login User : '.$request->user()->username, 'Admin Login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->auditService->create($request, 'Logout User : '.$request->user()->username, 'Logout');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
