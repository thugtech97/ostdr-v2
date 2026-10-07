<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SatelliteRequest;
use App\Models\Satellite;
use App\Services\AccessRightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satellites are the departments/sites a request can come from or go to.
 */
class SatelliteController extends Controller
{
    public const PAGE = 'Satellites';

    public function index(Request $request, AccessRightService $accessRights): Response
    {
        return Inertia::render('Satellites/Index', [
            // Legacy hides the parent "MCD" entry from maintenance.
            'satellites' => Satellite::query()
                ->where('name', '<>', 'MCD')
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
        return Inertia::render('Satellites/Create');
    }

    public function store(SatelliteRequest $request): RedirectResponse
    {
        Satellite::create($request->validated());

        return redirect()->route('satellites.index')->with('success', 'Satellite has been added.');
    }

    public function edit(Satellite $satellite): Response
    {
        return Inertia::render('Satellites/Edit', [
            'satellite' => $satellite->only(['id', 'name', 'description', 'active']),
        ]);
    }

    public function update(SatelliteRequest $request, Satellite $satellite): RedirectResponse
    {
        $satellite->update($request->validated());

        return redirect()->route('satellites.index')->with('success', 'Satellite has been updated.');
    }
}
