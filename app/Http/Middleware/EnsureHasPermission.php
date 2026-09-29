<?php

namespace App\Http\Middleware;

use App\Services\AccessRightService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:Users Maintenance,create')
 */
class EnsureHasPermission
{
    public function __construct(protected AccessRightService $accessRights)
    {
    }

    public function handle(Request $request, Closure $next, string $page, string $action = 'view'): Response
    {
        $user = $request->user();

        abort_unless($user && $this->accessRights->can($user, $page, $action), 403);

        return $next($request);
    }
}
