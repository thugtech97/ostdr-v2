<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Models\Permission;
use App\Services\AccessRightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The pages access rights are granted on. The description is what the app checks,
 * so renaming one changes which screens it controls.
 */
class PermissionController extends Controller
{
    public const PAGE = 'Permissions';

    public function index(Request $request, AccessRightService $accessRights): Response
    {
        return Inertia::render('Permissions/Index', [
            'permissions' => Permission::query()
                ->orderBy('description')
                ->get(['id', 'module_type', 'description', 'active']),
            'can' => [
                'create' => $accessRights->can($request->user(), self::PAGE, 'create'),
                'edit' => $accessRights->can($request->user(), self::PAGE, 'edit'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Permissions/Create', ['modules' => $this->modules()]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        Permission::create($request->validated());

        return redirect()->route('permissions.index')->with('success', 'Permission has been added.');
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('Permissions/Edit', [
            'permission' => $permission->only(['id', 'module_type', 'description', 'active']),
            'modules' => $this->modules(),
        ]);
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->validated());

        return redirect()->route('permissions.index')->with('success', 'Permission has been updated.');
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        return DB::table('modules')->orderBy('description')->pluck('description')->all();
    }
}
