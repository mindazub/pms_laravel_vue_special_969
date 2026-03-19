<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Project;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects and optionally a selected project's board.
     */
    public function index(Request $request, AttachmentService $attachmentService): Response
    {
        $projects = $request->user()
            ->projects()
            ->with('attachmentRecords')
            ->withCount([
                'notes',
                'notes as done_notes_count' => fn ($query) => $query->where('status', Note::STATUS_DONE),
            ])
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $projects->getCollection()->transform(function (Project $project) use ($attachmentService) {
            $totalNotes = (int) $project->notes_count;
            $doneNotes = (int) $project->done_notes_count;
            $completionPercentage = $totalNotes === 0
                ? 0
                : (int) round(($doneNotes / $totalNotes) * 100);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'attachments' => $this->serializeAttachments($project, $attachmentService),
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
                ->with(['attachmentRecords', 'notes.attachmentRecords'])
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
                    'attachments' => $this->serializeAttachments($project, $attachmentService),
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
                        'attachments' => $this->serializeAttachments($note, $attachmentService),
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
    public function store(Request $request, AttachmentService $attachmentService)
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
            'temp_attachment_ids' => ['nullable', 'array'],
            'temp_attachment_ids.*' => [
                'integer',
                Rule::exists('temporary_attachments', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ]);

        $project = $request->user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'attachments' => [],
        ]);

        $attachmentService->finalizeTemporaryAttachments(
            $project,
            $request->user(),
            $validated['temp_attachment_ids'] ?? []
        );

        return redirect()
            ->route('projects.index', ['project' => $project->id])
            ->with('success', 'Project created successfully.');
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project, AttachmentService $attachmentService)
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
            'temp_attachment_ids' => ['nullable', 'array'],
            'temp_attachment_ids.*' => [
                'integer',
                Rule::exists('temporary_attachments', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
            'selected_project_id' => ['nullable', 'integer'],
        ]);

        $ownedProject->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $attachmentService->finalizeTemporaryAttachments(
            $ownedProject,
            $request->user(),
            $validated['temp_attachment_ids'] ?? []
        );

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

    private function serializeAttachments(Project|Note $model, AttachmentService $attachmentService): array
    {
        if ($model->relationLoaded('attachmentRecords') && $model->attachmentRecords->isNotEmpty()) {
            return $model->attachmentRecords
                ->map(fn ($attachment) => $attachmentService->mapAttachment($attachment))
                ->values()
                ->all();
        }

        return collect($model->attachments ?? [])
            ->map(fn ($attachment) => $attachmentService->mapLegacyAttachment($attachment))
            ->values()
            ->all();
    }
}
