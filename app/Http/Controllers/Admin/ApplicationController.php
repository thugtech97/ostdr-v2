<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationRequest;
use App\Models\Application;
use App\Services\AccessRightService;
use App\Services\MaintenanceMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Application Maintenance: scheduled shutdowns (announced to every user beforehand),
 * stopping / starting the app, and the database re-index.
 */
class ApplicationController extends Controller
{
    public const PAGE = 'Application Maintenance';

    private const SORTABLE = ['scheduled_date', 'scheduled_time', 'reason'];

    public function index(Request $request, AccessRightService $accessRights): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);

        $sort = $filters['sort'] ?? 'id';

        $schedules = Application::query()->live()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('reason', 'like', "%{$search}%"))
            ->orderBy($sort, $filters['direction'] ?? 'desc')
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString()
            ->through(fn (Application $schedule) => [
                'id' => $schedule->id,
                'scheduled_date' => $schedule->scheduled_date,
                'scheduled_time' => substr((string) $schedule->scheduled_time, 0, 5),
                'reason' => $schedule->reason,
                'status' => $schedule->scheduledAt()->isFuture() ? 'Upcoming' : 'Done',
            ]);

        $user = $request->user();

        return Inertia::render('Applications/Index', [
            'schedules' => $schedules,
            'filters' => $filters,
            'can' => [
                'create' => $accessRights->can($user, self::PAGE, 'create'),
                'edit' => $accessRights->can($user, self::PAGE, 'edit'),
                'delete' => $accessRights->can($user, self::PAGE, 'delete'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Applications/Create');
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        Application::create([
            ...$request->validated(),
            'posted_date' => now()->toDateString(),
            'posted_time' => now()->format('H:i:s'),
        ]);

        return redirect()->route('applications.index')->with('success', 'Shutdown schedule has been added.');
    }

    public function edit(Application $application): Response
    {
        abort_if($application->deleted_at !== null, HttpResponse::HTTP_NOT_FOUND);

        return Inertia::render('Applications/Edit', [
            'schedule' => [
                'id' => $application->id,
                'scheduled_date' => $application->scheduled_date,
                'scheduled_time' => substr((string) $application->scheduled_time, 0, 5),
                'reason' => $application->reason,
            ],
        ]);
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        abort_if($application->deleted_at !== null, HttpResponse::HTTP_NOT_FOUND);

        $application->update($request->validated());

        return redirect()->route('applications.index')->with('success', 'Shutdown schedule has been updated.');
    }

    public function destroy(Application $application): RedirectResponse
    {
        $application->update(['deleted_at' => now()]);

        return back()->with('success', 'Shutdown schedule has been deleted.');
    }

    /**
     * Run the database's scheduled re-index procedure (legacy runScheduledIndexing).
     */
    public function reindex(): RedirectResponse
    {
        if (! DB::table('sys.procedures')->where('name', 'runScheduledIndexing')->exists()) {
            return back()->with('error', 'The re-index procedure (runScheduledIndexing) is not installed on this database.');
        }

        try {
            DB::statement('EXEC runScheduledIndexing');
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Re-indexing failed: '.$e->getMessage());
        }

        return back()->with('success', 'Re-indexing Successfully initiated!');
    }

    /**
     * Take the app down and sign everyone else out. The admin keeps access through the
     * maintenance bypass cookie, so they can start it again from here.
     */
    public function systemDown(Request $request, MaintenanceMode $maintenance): HttpResponse
    {
        $secret = $maintenance->down(keepSession: $request->session()->getId());

        return Inertia::location(url($secret));
    }

    public function systemUp(MaintenanceMode $maintenance): RedirectResponse
    {
        $maintenance->up();

        return back()->with('success', 'System Successfully started!');
    }
}
