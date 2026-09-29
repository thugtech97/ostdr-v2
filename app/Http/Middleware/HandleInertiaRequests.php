<?php

namespace App\Http\Middleware;

use App\Models\StockRequest;
use App\Services\AccessRightService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'username', 'role', 'dept']),
                'isAdmin' => (bool) $user?->isAdmin(),
                'permissions' => fn () => $user && ! $user->isAdmin()
                    ? app(AccessRightService::class)->permissionsFor($user)
                    : (object) [],
            ],
            // Sidebar badge: the user's unsaved drafts, as in legacy.
            'counts' => fn () => [
                'unsaved' => $user && app(AccessRightService::class)->can($user, 'Unsaved Stock Request')
                    ? StockRequest::query()->active()->where('isSaved', false)->where('created_by', $user->username)->count()
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
