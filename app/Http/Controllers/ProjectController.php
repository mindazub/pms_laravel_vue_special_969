<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['team:id,name', 'customer:id,name', 'projectManager:id,name'])
            ->withCount([
                'notes',
                'notes as done_notes_count' => fn ($query) => $query->where('status', Note::STATUS_DONE),
            ])
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $projects->getCollection()->transform(function (Project $project): array {
            $totalNotes = (int) $project->notes_count;
            $doneNotes = (int) $project->done_notes_count;
            $completionPercentage = $totalNotes === 0
                ? 0
                : (int) round(($doneNotes / $totalNotes) * 100);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'clipboard_text' => $project->clipboard_text,
                'attachments' => $project->attachments ?? [],
                'mentions' => $project->mentions ?? [],
                'team_id' => $project->team_id,
                'team_name' => $project->team?->name,
                'customer_id' => $project->customer_id,
                'customer_name' => $project->customer?->name,
                'project_manager_id' => $project->project_manager_id,
                'project_manager_name' => $project->projectManager?->name,
                'notes_count' => $totalNotes,
                'done_notes_count' => $doneNotes,
                'completion_percentage' => $completionPercentage,
                'is_done' => $totalNotes > 0 && $completionPercentage === 100,
            ];
        });

        $selectedProject = null;
        $projectNotes = [];
        $selectedProjectId = $request->integer('project');

        if ($selectedProjectId) {
            $project = Project::query()
                ->visibleTo($request->user())
                ->whereKey($selectedProjectId)
                ->withCount([
                    'notes',
                    'notes as done_notes_count' => fn ($query) => $query->where('status', Note::STATUS_DONE),
                ])
                ->with([
                    'team:id,name',
                    'customer:id,name',
                    'projectManager:id,name',
                    'notes.assignees:id,name',
                ])
                ->first();

            if ($project) {
                $totalNotes = (int) $project->notes_count;
                $doneNotes = (int) $project->done_notes_count;
                $completionPercentage = $totalNotes === 0
                    ? 0
                    : (int) round(($doneNotes / $totalNotes) * 100);

                $selectedProject = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'start_date' => $project->start_date?->toDateString(),
                    'end_date' => $project->end_date?->toDateString(),
                    'clipboard_text' => $project->clipboard_text,
                    'attachments' => $project->attachments ?? [],
                    'mentions' => $project->mentions ?? [],
                    'team_id' => $project->team_id,
                    'team_name' => $project->team?->name,
                    'customer_id' => $project->customer_id,
                    'customer_name' => $project->customer?->name,
                    'project_manager_id' => $project->project_manager_id,
                    'project_manager_name' => $project->projectManager?->name,
                    'notes_count' => $totalNotes,
                    'done_notes_count' => $doneNotes,
                    'completion_percentage' => $completionPercentage,
                    'is_done' => $totalNotes > 0 && $completionPercentage === 100,
                ];

                $projectNotes = $project->notes
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (Note $note): array => [
                        'id' => $note->id,
                        'title' => $note->title,
                        'content' => $note->content,
                        'clipboard_text' => $note->clipboard_text,
                        'attachments' => $note->attachments ?? [],
                        'mentions' => $note->mentions ?? [],
                        'status' => $note->status,
                        'progress' => (int) $note->progress,
                        'estimated_time_hours' => $note->estimated_time_hours,
                        'assignee_ids' => $note->assignees->pluck('id')->values()->all(),
                        'assignee_names' => $note->assignees->pluck('name')->values()->all(),
                    ]);
            }
        }

        $tasksCreatedToday = Note::query()
            ->where('user_id', $request->user()->id)
            ->where('scope', Note::SCOPE_GENERAL)
            ->whereDate('created_at', CarbonImmutable::today())
            ->count();

        $teams = Team::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name']);

        $customers = Customer::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'notes' => $projectNotes,
            'statuses' => Note::STATUSES,
            'progressSteps' => [0, 25, 50, 75, 100],
            'teams' => $teams,
            'customers' => $customers,
            'users' => $users,
            'defaultTaskEstimateHours' => $this->defaultEstimatedHoursForPosition($tasksCreatedToday + 1),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $projectData = [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'clipboard_text' => $request->validated('clipboard_text'),
            'attachments' => [],
            'mentions' => $request->validated('mentions') ?? [],
            'team_id' => $request->validated('team_id'),
            'customer_id' => $request->validated('customer_id'),
            'project_manager_id' => $request->validated('project_manager_id') ?? $request->user()->id,
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
        ];

        $project = $request->user()->projects()->create($projectData);

        if ($request->hasFile('attachments')) {
            $project->update([
                'attachments' => $this->storeUploadedFiles($request->file('attachments'), "projects/{$project->id}"),
            ]);
        }

        return redirect()
            ->route('projects.index', ['project' => $project->id])
            ->with('success', 'Project created successfully.');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        $attachments = $project->attachments ?? [];

        if ($request->hasFile('attachments')) {
            $attachments = array_merge(
                $attachments,
                $this->storeUploadedFiles($request->file('attachments'), "projects/{$project->id}")
            );
        }

        $project->update([
            'name' => $validated['name'] ?? $project->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $project->description,
            'clipboard_text' => array_key_exists('clipboard_text', $validated) ? $validated['clipboard_text'] : $project->clipboard_text,
            'attachments' => $attachments,
            'mentions' => array_key_exists('mentions', $validated) ? $validated['mentions'] : ($project->mentions ?? []),
            'team_id' => array_key_exists('team_id', $validated) ? $validated['team_id'] : $project->team_id,
            'customer_id' => array_key_exists('customer_id', $validated) ? $validated['customer_id'] : $project->customer_id,
            'project_manager_id' => array_key_exists('project_manager_id', $validated) ? $validated['project_manager_id'] : $project->project_manager_id,
            'start_date' => array_key_exists('start_date', $validated) ? $validated['start_date'] : $project->start_date,
            'end_date' => array_key_exists('end_date', $validated) ? $validated['end_date'] : $project->end_date,
        ]);

        $selectedProjectId = (int) ($validated['selected_project_id'] ?? $project->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Project updated successfully.',
                'project_id' => $project->id,
                'selected_project_id' => $selectedProjectId,
                'start_date' => $project->fresh()->start_date?->toDateString(),
                'end_date' => $project->fresh()->end_date?->toDateString(),
            ]);
        }

        return redirect()
            ->route('projects.index', ['project' => $selectedProjectId])
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $selectedProjectId = $request->integer('selected_project_id');
        $project->delete();

        $fallbackProjectId = Project::query()->visibleTo($request->user())->latest('id')->value('id');

        if ($selectedProjectId && $selectedProjectId !== $project->id) {
            $fallbackProjectId = $selectedProjectId;
        }

        return redirect()
            ->route('projects.index', $fallbackProjectId ? ['project' => $fallbackProjectId] : [])
            ->with('success', 'Project deleted successfully.');
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     * @return array<int, array{original_name: string, path: string, mime_type: string, size: int, url: string}>
     */
    private function storeUploadedFiles(?array $files, string $directory): array
    {
        if (! $files) {
            return [];
        }

        return collect($files)
            ->map(function (UploadedFile $file) use ($directory): array {
                $path = $file->store($directory, 'public');

                return [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                    'size' => (int) $file->getSize(),
                    'url' => asset('storage/'.$path),
                ];
            })
            ->values()
            ->all();
    }

    private function defaultEstimatedHoursForPosition(int $position): float
    {
        if ($position <= 1) {
            return 8.0;
        }

        return max(0.5, round(8 / (2 ** ($position - 1)), 2));
    }
}
