<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    private const PROGRESS_STEPS = [0, 25, 50, 75, 100];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $notes = $request->user()->notes()->latest()->get();

        if ($request->expectsJson()) {
            return response()->json($notes);
        }

        return Inertia::render('Notes/Index', [
            'notes' => $notes,
            'statuses' => Note::STATUSES,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, AttachmentService $attachmentService)
    {
        $this->authorize('create', Note::class);

        $validated = $request->validate([
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status' => ['nullable', Rule::in(Note::STATUSES)],
            'progress' => ['nullable', Rule::in(self::PROGRESS_STEPS)],
            'temp_attachment_ids' => ['nullable', 'array'],
            'temp_attachment_ids.*' => [
                'integer',
                Rule::exists('temporary_attachments', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ]);

        $validated['status'] = $validated['status'] ?? Note::STATUS_TODO;
        $validated['progress'] = $validated['progress'] ?? 0;

        if ((int) $validated['progress'] === 100 || $validated['status'] === Note::STATUS_DONE) {
            $validated['status'] = Note::STATUS_DONE;
            $validated['progress'] = 100;
        } elseif ($validated['status'] === Note::STATUS_TODO) {
            $validated['progress'] = 0;
        }

        $note = $request->user()->notes()->create([
            'project_id' => $validated['project_id'],
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'status' => $validated['status'],
            'progress' => $validated['progress'],
        ]);

        $attachmentService->finalizeTemporaryAttachments(
            $note,
            $request->user(),
            $validated['temp_attachment_ids'] ?? []
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('projects.index', ['project' => $note->project_id])
                ->with('success', 'Note created successfully.');
        }

        return response()->json($note, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note)
    {
        $this->authorize('view', $note);

        return response()->json($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note, AttachmentService $attachmentService)
    {
        $this->authorize('update', $note);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|nullable|string',
            'status' => ['sometimes', Rule::in(Note::STATUSES)],
            'progress' => ['sometimes', Rule::in(self::PROGRESS_STEPS)],
            'temp_attachment_ids' => ['sometimes', 'array'],
            'temp_attachment_ids.*' => [
                'integer',
                Rule::exists('temporary_attachments', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ]);

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

        $tempAttachmentIds = $validated['temp_attachment_ids'] ?? [];
        unset($validated['temp_attachment_ids']);

        $note->update($validated);

        $attachmentService->finalizeTemporaryAttachments($note, $request->user(), $tempAttachmentIds);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('projects.index', ['project' => $note->project_id])
                ->with('success', 'Note updated successfully.');
        }

        return response()->json($note);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
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
}
