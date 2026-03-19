<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\TemporaryAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array<string, mixed>>
     */
    public function storeTemporaryFiles(User $user, array $files): array
    {
        return collect($files)
            ->map(function (UploadedFile $file) use ($user) {
                [$width, $height] = $this->extractDimensions($file->getRealPath());

                $temporaryAttachment = TemporaryAttachment::create([
                    'user_id' => $user->id,
                    'disk' => 'public',
                    'path' => $file->store("temporary-attachments/{$user->id}", 'public'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                    'size' => (int) ($file->getSize() ?? 0),
                    'width' => $width,
                    'height' => $height,
                ]);

                return $this->mapTemporaryAttachment($temporaryAttachment);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $temporaryAttachmentIds
     */
    public function finalizeTemporaryAttachments(Model $attachable, User $user, array $temporaryAttachmentIds): void
    {
        if ($temporaryAttachmentIds === []) {
            return;
        }

        $temporaryAttachments = TemporaryAttachment::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $temporaryAttachmentIds)
            ->get()
            ->keyBy('id');

        $sortOrder = (int) ($attachable->attachmentRecords()->max('sort_order') ?? 0);

        foreach ($temporaryAttachmentIds as $temporaryAttachmentId) {
            $temporaryAttachment = $temporaryAttachments->get($temporaryAttachmentId);

            if (! $temporaryAttachment) {
                continue;
            }

            $extension = pathinfo($temporaryAttachment->path, PATHINFO_EXTENSION);
            $targetPath = $this->permanentDirectory($attachable).'/'.Str::uuid().($extension ? '.'.$extension : '');

            if (! Storage::disk($temporaryAttachment->disk)->move($temporaryAttachment->path, $targetPath)) {
                continue;
            }

            $sortOrder++;

            $attachable->attachmentRecords()->create([
                'disk' => $temporaryAttachment->disk,
                'path' => $targetPath,
                'original_name' => $temporaryAttachment->original_name,
                'mime_type' => $temporaryAttachment->mime_type,
                'size' => $temporaryAttachment->size,
                'width' => $temporaryAttachment->width,
                'height' => $temporaryAttachment->height,
                'uploaded_by' => $user->id,
                'sort_order' => $sortOrder,
            ]);

            $temporaryAttachment->delete();
        }
    }

    public function deleteTemporaryAttachment(TemporaryAttachment $temporaryAttachment): void
    {
        Storage::disk($temporaryAttachment->disk)->delete($temporaryAttachment->path);
        $temporaryAttachment->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapAttachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'path' => $attachment->path,
            'mime_type' => $attachment->mime_type,
            'size' => (int) $attachment->size,
            'width' => $attachment->width,
            'height' => $attachment->height,
            'url' => route('attachments.show', $attachment),
            'is_image' => $this->isImageMimeType($attachment->mime_type),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapTemporaryAttachment(TemporaryAttachment $temporaryAttachment): array
    {
        return [
            'id' => $temporaryAttachment->id,
            'original_name' => $temporaryAttachment->original_name,
            'path' => $temporaryAttachment->path,
            'mime_type' => $temporaryAttachment->mime_type,
            'size' => (int) $temporaryAttachment->size,
            'width' => $temporaryAttachment->width,
            'height' => $temporaryAttachment->height,
            'url' => route('temporary-attachments.show', $temporaryAttachment),
            'is_image' => $this->isImageMimeType($temporaryAttachment->mime_type),
        ];
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>
     */
    public function mapLegacyAttachment(array $attachment): array
    {
        return [
            'id' => $attachment['id'] ?? null,
            'original_name' => $attachment['original_name'] ?? basename((string) ($attachment['path'] ?? 'attachment')),
            'path' => $attachment['path'] ?? null,
            'mime_type' => $attachment['mime_type'] ?? null,
            'size' => (int) ($attachment['size'] ?? 0),
            'width' => $attachment['width'] ?? null,
            'height' => $attachment['height'] ?? null,
            'url' => $attachment['url'] ?? (isset($attachment['path']) ? asset('storage/'.$attachment['path']) : null),
            'is_image' => $this->isImageMimeType($attachment['mime_type'] ?? null),
        ];
    }

    private function permanentDirectory(Model $attachable): string
    {
        return match (class_basename($attachable)) {
            'Project' => "project-attachments/{$attachable->getKey()}",
            'Note' => "note-attachments/{$attachable->getKey()}",
            default => "attachments/{$attachable->getTable()}/{$attachable->getKey()}",
        };
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function extractDimensions(?string $realPath): array
    {
        if (! $realPath || ! is_file($realPath)) {
            return [null, null];
        }

        $imageSize = @getimagesize($realPath);

        if (! $imageSize) {
            return [null, null];
        }

        return [$imageSize[0] ?? null, $imageSize[1] ?? null];
    }

    private function isImageMimeType(?string $mimeType): bool
    {
        return is_string($mimeType) && str_starts_with($mimeType, 'image/');
    }
}
