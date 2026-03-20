<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    private const PROGRESS_STEPS = [0, 25, 50, 75, 100];

    public function index(Request $request): Response|JsonResponse
    {
        $notes = Note::query()
            ->whereHas('project', fn ($query) => $query->visibleTo($request->user()))
            ->latest()
            ->get();

        if ($request->expectsJson()) {
            return response()->json($notes);
        }

        return Inertia::render('Notes/Index', [
            'notes' => $notes,
            'statuses' => Note::STATUSES,
        ]);
    }

    public function store(StoreNoteRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Note::class);

        $project = Project::query()
            ->visibleTo($request->user())
            ->whereKey($request->validated('project_id'))
            ->firstOrFail();

        $payload = [
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'title' => $request->validated('title'),
            'content' => $request->validated('content') ?? '',
            'clipboard_text' => $request->validated('clipboard_text'),
            'status' => $request->validated('status') ?? Note::STATUS_TODO,
            'progress' => $request->validated('progress') ?? 0,
            'mentions' => $request->validated('mentions') ?? [],
            'attachments' => $this->storeUploadedFiles($request->file('attachments'), "notes/temp-{$request->user()->id}"),
        ];

        if ((int) $payload['progress'] === 100 || $payload['status'] === Note::STATUS_DONE) {
            $payload['status'] = Note::STATUS_DONE;
            $payload['progress'] = 100;
        } elseif ($payload['status'] === Note::STATUS_TODO) {
            $payload['progress'] = 0;
        }

        $note = $request->user()->notes()->create($payload);
        $note->assignees()->sync($request->validated('assignee_ids') ?? []);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('projects.index', ['project' => $note->project_id])
                ->with('success', 'Note created successfully.');
        }

        return response()->json($note->load('assignees:id,name'), 201);
    }

    public function show(Note $note): JsonResponse
    {
        $this->authorize('view', $note);

        return response()->json($note->load('assignees:id,name'));
    }

    public function update(UpdateNoteRequest $request, Note $note): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $note);

        $validated = $request->validated();

        if (array_key_exists('progress', $validated) && (int) $validated['progress'] === 100) {
            $validated['status'] = Note::STATUS_DONE;
        }

        if (array_key_exists('status', $validated)) {
            if ($validated['status'] === Note::STATUS_DONE) {
                $validated['progress'] = 100;
            }

            if ($validated['status'] === Note::STATUS_TODO) {
                $validated['progress'] = 0;
            }

            if ($validated['status'] === Note::STATUS_IN_PROGRESS && ! array_key_exists('progress', $validated)) {
                $validated['progress'] = $note->progress === 100 ? 75 : $note->progress;
            }
        }

        if (array_key_exists('content', $validated) && $validated['content'] === null) {
            $validated['content'] = '';
        }

        if ($request->hasFile('attachments')) {
            $validated['attachments'] = array_merge(
                $note->attachments ?? [],
                $this->storeUploadedFiles($request->file('attachments'), "notes/{$note->id}")
            );
        }

        if (array_key_exists('mentions', $validated)) {
            $note->mentions = $validated['mentions'];
        }

        $note->update($validated);

        if (array_key_exists('assignee_ids', $validated)) {
            $note->assignees()->sync($validated['assignee_ids'] ?? []);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('projects.index', ['project' => $note->project_id])
                ->with('success', 'Note updated successfully.');
        }

        return response()->json($note->load('assignees:id,name'));
    }

    public function destroy(Note $note): RedirectResponse|JsonResponse|HttpResponse
    {
        $this->authorize('delete', $note);

        $projectId = $note->project_id;
        $note->delete();

        if (! request()->expectsJson()) {
            return redirect()
                ->route('projects.index', ['project' => $projectId])
                ->with('success', 'Note deleted successfully.');
        }

        return response()->noContent();
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
}
