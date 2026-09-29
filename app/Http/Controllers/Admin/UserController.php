<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\Satellite;
use App\Models\User;
use App\Services\AccessRightService;
use App\Services\HrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    public const PAGE = 'Users Maintenance';

    private const SORTABLE = ['name', 'username', 'dept', 'role', 'email', 'isActive'];

    public function index(Request $request, AccessRightService $accessRights): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);

        $users = User::query()
            ->manageable()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    foreach (['name', 'username', 'dept', 'role', 'email'] as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 10, ['id', 'name', 'username', 'dept', 'role', 'email', 'isActive'])
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'can' => [
                'create' => $accessRights->can($request->user(), self::PAGE, 'create'),
                'edit' => $accessRights->can($request->user(), self::PAGE, 'edit'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', $this->formOptions());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create([...$request->userAttributes(), 'isActive' => 1]);

        return redirect()->route('users.index')->with('success', 'User has been added.');
    }

    public function edit(User $user): Response
    {
        $this->ensureManageable($user);

        return Inertia::render('Users/Edit', [
            ...$this->formOptions($user),
            'user' => $user->only(['id', 'name', 'username', 'dept', 'email', 'role_id', 'isActive']),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $user->update($request->userAttributes());

        return redirect()->route('users.index')->with('success', 'User has been updated.');
    }

    public function activate(User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $user->update(['isActive' => 1]);

        return back()->with('success', "{$user->username} has been activated.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['isActive' => 0]);

        return back()->with('success', "{$user->username} has been deactivated.");
    }

    /**
     * Employee search for the create form, backed by the HR master.
     */
    public function employees(Request $request, HrisService $hris): JsonResponse
    {
        $validated = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']]);

        try {
            return response()->json($hris->searchEmployees($validated['search']));
        } catch (Throwable $e) {
            Log::warning('HRIS employee lookup failed', ['exception' => $e->getMessage()]);

            return response()->json(['message' => 'The HRIS employee list is unavailable right now.'], 503);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?User $user = null): array
    {
        $roles = Role::query()
            ->assignable()
            ->where(fn ($query) => $query->where('active', true)->orWhere('id', $user?->role_id ?? 0))
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        $departments = collect(Satellite::departmentOptions());

        // Keep a legacy department that is no longer a satellite selectable for its user.
        if ($user?->dept && ! $departments->contains($user->dept)) {
            $departments = $departments->push($user->dept)->sort()->values();
        }

        return [
            'roles' => $roles,
            'departments' => $departments,
        ];
    }

    /**
     * The built-in ADMIN account is not managed here, same as legacy.
     */
    private function ensureManageable(User $user): void
    {
        abort_if(strtoupper((string) $user->username) === 'ADMIN', 404);
    }
}
