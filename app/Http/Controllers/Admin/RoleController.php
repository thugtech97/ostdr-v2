<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessRightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public const PAGE = 'Roles';

    public function index(Request $request, AccessRightService $accessRights): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->assignable()
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'active']),
            'can' => [
                'create' => $accessRights->can($request->user(), self::PAGE, 'create'),
                'edit' => $accessRights->can($request->user(), self::PAGE, 'edit'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Create');
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        Role::create($request->validated());

        return redirect()->route('roles.index')->with('success', 'Role has been added.');
    }

    public function edit(Role $role): Response
    {
        $this->ensureAssignable($role);

        return Inertia::render('Roles/Edit', [
            'role' => $role->only(['id', 'name', 'description', 'active']),
            'usersCount' => $role->users()->count(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureAssignable($role);

        DB::transaction(function () use ($request, $role) {
            $role->update($request->validated());

            // Users carry a copy of their role's name (legacy schema); keep it in step with a rename.
            if ($role->wasChanged('name')) {
                User::where('role_id', $role->id)->each(fn (User $user) => $user->update(['role' => $role->name]));
            }
        });

        return redirect()->route('roles.index')->with('success', 'Role has been updated.');
    }

    private function ensureAssignable(Role $role): void
    {
        abort_if(strtoupper($role->name) === 'ADMIN', 404);
    }
}
