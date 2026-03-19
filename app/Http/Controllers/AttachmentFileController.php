<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\TemporaryAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentFileController extends Controller
{
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $attachable = $attachment->attachable;

        abort_unless($attachable && $attachable->user_id === $request->user()->id, 403);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            ]
        );
    }

    public function showTemporary(Request $request, TemporaryAttachment $temporaryAttachment): StreamedResponse
    {
        abort_unless($temporaryAttachment->user_id === $request->user()->id, 403);

        return Storage::disk($temporaryAttachment->disk)->response(
            $temporaryAttachment->path,
            $temporaryAttachment->original_name,
            [
                'Content-Type' => $temporaryAttachment->mime_type ?? 'application/octet-stream',
            ]
        );
    }
}