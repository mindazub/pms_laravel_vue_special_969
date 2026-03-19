<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\Workload2025ImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Projects2025Controller extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($request, $filters);

        $sort = in_array($request->query('sort'), ['source_date_added', 'work_week_number', 'owner_name_raw', 'estimated_time_hours', 'progress', 'title'], true)
            ? $request->query('sort')
            : 'source_date_added';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $rows = (clone $query)
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->through(fn (Note $note) => [
                'id' => $note->id,
                'owner' => $note->owner_name_raw,
                'week_label' => $note->work_week_label,
                'week_number' => $note->work_week_number,
                'task' => $note->title,
                'category' => $note->category,
                'linked_goal' => $note->linked_goal,
                'priority' => $note->priority_code,
                'estimated_time_hours' => $note->estimated_time_hours,
                'status' => $note->workload_status,
                'progress' => $note->progress,
                'comments' => $note->content,
                'moved_to_next_week' => $note->moved_to_next_week,
                'replaced_task' => $note->replaced_task,
                'source_date_added' => $note->source_date_added?->toDateString(),
            ])
            ->withQueryString();

        $summaryBase = $this->filteredQuery($request, $filters);
        $totalTasks = (clone $summaryBase)->count();
        $doneTasks = (clone $summaryBase)->where('status', Note::STATUS_DONE)->count();
        $inProgressTasks = (clone $summaryBase)->where('status', Note::STATUS_IN_PROGRESS)->count();
        $notStartedTasks = (clone $summaryBase)->where('status', Note::STATUS_TODO)->count();
        $totalHours = (float) ((clone $summaryBase)->sum('estimated_time_hours') ?: 0);

        return Inertia::render('Projects2025/Index', [
            'rows' => $rows,
            'filters' => array_merge($filters, ['sort' => $sort, 'direction' => $direction]),
            'filterOptions' => $this->filterOptions($request),
            'summary' => [
                'total_tasks' => $totalTasks,
                'done_tasks' => $doneTasks,
                'in_progress_tasks' => $inProgressTasks,
                'not_started_tasks' => $notStartedTasks,
                'completion_rate' => $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0,
                'total_estimated_hours' => round($totalHours, 2),
                'moved_count' => (clone $summaryBase)->where('moved_to_next_week', true)->count(),
            ],
            'importSummary' => $request->session()->get('workload2025ImportSummary'),
        ]);
    }

    public function import(Request $request, Workload2025ImportService $importService): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $summary = $importService->importFromPath(
            $validated['file']->getRealPath(),
            $request->user(),
            (bool) ($validated['dry_run'] ?? false)
        );

        return redirect()
            ->route('projects.2025')
            ->with('success', $summary['dry_run']
                ? 'Dry run completed for the 2025 workload CSV.'
                : '2025 workload CSV imported successfully.')
            ->with('workload2025ImportSummary', $summary);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'owner' => trim((string) $request->query('owner', '')),
            'week' => trim((string) $request->query('week', '')),
            'category' => trim((string) $request->query('category', '')),
            'linked_goal' => trim((string) $request->query('linked_goal', '')),
            'priority' => trim((string) $request->query('priority', '')),
            'workload_status' => trim((string) $request->query('workload_status', '')),
            'moved' => trim((string) $request->query('moved', '')),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredQuery(Request $request, array $filters): Builder|HasMany
    {
        $query = $request->user()->notes()->workload2025();

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('linked_goal', 'like', "%{$search}%")
                    ->orWhere('owner_name_raw', 'like', "%{$search}%");
            });
        }

        foreach (['owner' => 'owner_name_raw', 'week' => 'work_week_label', 'category' => 'category', 'linked_goal' => 'linked_goal', 'priority' => 'priority_code', 'workload_status' => 'workload_status'] as $filter => $column) {
            if ($filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
        }

        if ($filters['moved'] === 'yes') {
            $query->where('moved_to_next_week', true);
        }

        if ($filters['moved'] === 'no') {
            $query->where('moved_to_next_week', false);
        }

        return $query;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function filterOptions(Request $request): array
    {
        $query = $request->user()->notes()->workload2025();

        return [
            'owners' => (clone $query)->whereNotNull('owner_name_raw')->distinct()->orderBy('owner_name_raw')->pluck('owner_name_raw')->values()->all(),
            'weeks' => (clone $query)->whereNotNull('work_week_number')->orderBy('work_week_number')->distinct()->pluck('work_week_label')->values()->all(),
            'categories' => (clone $query)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->values()->all(),
            'linked_goals' => (clone $query)->whereNotNull('linked_goal')->distinct()->orderBy('linked_goal')->pluck('linked_goal')->values()->all(),
            'priorities' => (clone $query)->whereNotNull('priority_code')->distinct()->orderBy('priority_code')->pluck('priority_code')->values()->all(),
            'statuses' => (clone $query)->whereNotNull('workload_status')->distinct()->orderBy('workload_status')->pluck('workload_status')->values()->all(),
        ];
    }
}
