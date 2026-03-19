<?php

namespace App\Http\Controllers;

use App\Models\TemporaryAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemporaryAttachmentController extends Controller
{
    public function store(Request $request, AttachmentService $attachmentService): JsonResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ]);

        return response()->json([
            'data' => $attachmentService->storeTemporaryFiles($request->user(), $validated['files']),
        ], 201);
    }

    public function destroy(Request $request, TemporaryAttachment $temporaryAttachment, AttachmentService $attachmentService): JsonResponse
    {
        abort_unless($temporaryAttachment->user_id === $request->user()->id, 403);

        $attachmentService->deleteTemporaryAttachment($temporaryAttachment);

        return response()->json([], 204);
    }
}
