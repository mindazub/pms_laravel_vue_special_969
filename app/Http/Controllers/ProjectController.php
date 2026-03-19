<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects and optionally a selected project's board.
     */
    public function index(Request $request): Response
    {
        $projects = $request->user()
            ->projects()
            ->withCount([
                'notes',
                'notes as done_notes_count' => fn ($query) => $query->where('status', Note::STATUS_DONE),
            ])
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $projects->getCollection()->transform(function (Project $project) {
            $totalNotes = (int) $project->notes_count;
            $doneNotes = (int) $project->done_notes_count;
            $completionPercentage = $totalNotes === 0
                ? 0
                : (int) round(($doneNotes / $totalNotes) * 100);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'attachments' => $project->attachments ?? [],
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
            $project = $request->user()
                ->projects()
                ->whereKey($selectedProjectId)
                ->withCount([
                    'notes',
                    'notes as done_notes_count' => fn ($query) => $query->where('status', Note::STATUS_DONE),
                ])
                ->with('notes')
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
                    'attachments' => $project->attachments ?? [],
                    'notes_count' => $totalNotes,
                    'done_notes_count' => $doneNotes,
                    'completion_percentage' => $completionPercentage,
                    'is_done' => $totalNotes > 0 && $completionPercentage === 100,
                ];

                $projectNotes = $project->notes
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (Note $note) => [
                        'id' => $note->id,
                        'title' => $note->title,
                        'content' => $note->content,
                        'attachments' => $note->attachments ?? [],
                        'status' => $note->status,
                        'progress' => (int) $note->progress,
                    ]);
            }
        }

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'notes' => $projectNotes,
            'statuses' => Note::STATUSES,
            'progressSteps' => [0, 25, 50, 75, 100],
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
            'description' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $project = $request->user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'attachments' => [],
        ]);

        if ($request->hasFile('attachments')) {
            $project->update([
                'attachments' => $this->storeUploadedFiles($request->file('attachments'), "projects/{$project->id}"),
            ]);
        }

        return redirect()
            ->route('projects.index', ['project' => $project->id])
            ->with('success', 'Project created successfully.');
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project)
    {
        $ownedProject = $request->user()->projects()->whereKey($project->id)->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')
                    ->where(fn ($query) => $query->where('user_id', $request->user()->id))
                    ->ignore($ownedProject->id),
            ],
            'description' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'selected_project_id' => ['nullable', 'integer'],
        ]);

        $attachments = $ownedProject->attachments ?? [];

        if ($request->hasFile('attachments')) {
            $attachments = array_merge(
                $attachments,
                $this->storeUploadedFiles($request->file('attachments'), "projects/{$ownedProject->id}")
            );
        }

        $ownedProject->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'attachments' => $attachments,
        ]);

        $selectedProjectId = (int) ($validated['selected_project_id'] ?? $ownedProject->id);

        return redirect()
            ->route('projects.index', ['project' => $selectedProjectId])
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request, Project $project)
    {
        $ownedProject = $request->user()->projects()->whereKey($project->id)->firstOrFail();

        $selectedProjectId = $request->integer('selected_project_id');

        $ownedProject->delete();

        $fallbackProjectId = $request->user()->projects()->latest('id')->value('id');

        if ($selectedProjectId && $selectedProjectId !== $ownedProject->id) {
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
            ->map(function (UploadedFile $file) use ($directory) {
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
}
