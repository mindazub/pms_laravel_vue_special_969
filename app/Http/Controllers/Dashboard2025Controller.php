<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard2025Controller extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = [
            'owner' => trim((string) $request->query('owner', '')),
            'week' => trim((string) $request->query('week', '')),
            'category' => trim((string) $request->query('category', '')),
            'linked_goal' => trim((string) $request->query('linked_goal', '')),
        ];

        $query = $this->filteredQuery($request, $filters);

        $totalTasks = (clone $query)->count();
        $doneTasks = (clone $query)->where('status', Note::STATUS_DONE)->count();
        $inProgressTasks = (clone $query)->where('status', Note::STATUS_IN_PROGRESS)->count();
        $notStartedTasks = (clone $query)->where('status', Note::STATUS_TODO)->count();
        $blockedTasks = (clone $query)->where('workload_status', 'blocked')->count();
        $totalHours = (float) ((clone $query)->sum('estimated_time_hours') ?: 0);
        $completedHours = (float) ((clone $query)->selectRaw('COALESCE(SUM(COALESCE(estimated_time_hours, 0) * progress / 100.0), 0) as completed_hours')->value('completed_hours') ?: 0);

        $ownerBreakdown = (clone $query)
            ->select('owner_name_raw')
            ->selectRaw('COUNT(*) as tasks_count')
            ->selectRaw('COALESCE(SUM(estimated_time_hours), 0) as estimated_hours')
            ->selectRaw('COALESCE(AVG(progress), 0) as avg_progress')
            ->groupBy('owner_name_raw')
            ->orderByDesc('tasks_count')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'owner' => $row->owner_name_raw,
                'tasks_count' => (int) $row->tasks_count,
                'estimated_hours' => round((float) $row->estimated_hours, 2),
                'avg_progress' => (int) round((float) $row->avg_progress),
            ]);

        $weeklyTrend = (clone $query)
            ->select('work_week_label', 'work_week_number')
            ->selectRaw('COUNT(*) as tasks_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done_count', [Note::STATUS_DONE])
            ->selectRaw('COALESCE(SUM(estimated_time_hours), 0) as estimated_hours')
            ->groupBy('work_week_label', 'work_week_number')
            ->orderBy('work_week_number')
            ->get()
            ->map(fn ($row) => [
                'week_label' => $row->work_week_label,
                'week_number' => (int) $row->work_week_number,
                'tasks_count' => (int) $row->tasks_count,
                'done_count' => (int) $row->done_count,
                'estimated_hours' => round((float) $row->estimated_hours, 2),
            ]);

        $categoryBreakdown = (clone $query)
            ->whereNotNull('category')
            ->select('category')
            ->selectRaw('COUNT(*) as tasks_count')
            ->groupBy('category')
            ->orderByDesc('tasks_count')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'tasks_count' => (int) $row->tasks_count,
            ]);

        $goalBreakdown = (clone $query)
            ->whereNotNull('linked_goal')
            ->select('linked_goal')
            ->selectRaw('COUNT(*) as tasks_count')
            ->groupBy('linked_goal')
            ->orderByDesc('tasks_count')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'linked_goal' => $row->linked_goal,
                'tasks_count' => (int) $row->tasks_count,
            ]);

        $blockers = (clone $query)
            ->where(function (Builder $builder) {
                $builder
                    ->where('moved_to_next_week', true)
                    ->orWhere('workload_status', 'blocked')
                    ->orWhereNotNull('content');
            })
            ->orderByDesc('moved_to_next_week')
            ->orderByDesc('source_date_added')
            ->limit(10)
            ->get()
            ->map(fn (Note $note) => [
                'id' => $note->id,
                'owner' => $note->owner_name_raw,
                'task' => $note->title,
                'week_label' => $note->work_week_label,
                'status' => $note->workload_status,
                'moved_to_next_week' => $note->moved_to_next_week,
                'comments' => $note->content,
            ]);

        return Inertia::render('Dashboard2025', [
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($request),
            'kpis' => [
                'total_tasks' => $totalTasks,
                'done_tasks' => $doneTasks,
                'in_progress_tasks' => $inProgressTasks,
                'not_started_tasks' => $notStartedTasks,
                'blocked_tasks' => $blockedTasks,
                'completion_rate' => $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0,
                'total_estimated_hours' => round($totalHours, 2),
                'completed_hours' => round($completedHours, 2),
                'moved_count' => (clone $query)->where('moved_to_next_week', true)->count(),
            ],
            'ownerBreakdown' => $ownerBreakdown,
            'weeklyTrend' => $weeklyTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'goalBreakdown' => $goalBreakdown,
            'blockers' => $blockers,
        ]);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredQuery(Request $request, array $filters): Builder|HasMany
    {
        $query = $request->user()->notes()->workload2025();

        foreach (['owner' => 'owner_name_raw', 'week' => 'work_week_label', 'category' => 'category', 'linked_goal' => 'linked_goal'] as $filter => $column) {
            if ($filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
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
        ];
    }
}
