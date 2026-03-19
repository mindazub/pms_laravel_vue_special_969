<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (Schema::hasColumn('projects', 'attachments')) {
            $projects = DB::table('projects')
                ->select('id', 'user_id', 'attachments')
                ->whereNotNull('attachments')
                ->get();

            foreach ($projects as $project) {
                $attachments = json_decode($project->attachments, true);

                if (! is_array($attachments)) {
                    continue;
                }

                foreach (array_values($attachments) as $index => $attachment) {
                    DB::table('attachments')->insert([
                        'attachable_type' => 'App\\Models\\Project',
                        'attachable_id' => $project->id,
                        'disk' => 'public',
                        'path' => $attachment['path'] ?? '',
                        'original_name' => $attachment['original_name'] ?? basename($attachment['path'] ?? 'attachment'),
                        'mime_type' => $attachment['mime_type'] ?? null,
                        'size' => (int) ($attachment['size'] ?? 0),
                        'width' => null,
                        'height' => null,
                        'uploaded_by' => $project->user_id,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasColumn('notes', 'attachments')) {
            $notes = DB::table('notes')
                ->select('id', 'user_id', 'attachments')
                ->whereNotNull('attachments')
                ->get();

            foreach ($notes as $note) {
                $attachments = json_decode($note->attachments, true);

                if (! is_array($attachments)) {
                    continue;
                }

                foreach (array_values($attachments) as $index => $attachment) {
                    DB::table('attachments')->insert([
                        'attachable_type' => 'App\\Models\\Note',
                        'attachable_id' => $note->id,
                        'disk' => 'public',
                        'path' => $attachment['path'] ?? '',
                        'original_name' => $attachment['original_name'] ?? basename($attachment['path'] ?? 'attachment'),
                        'mime_type' => $attachment['mime_type'] ?? null,
                        'size' => (int) ($attachment['size'] ?? 0),
                        'width' => null,
                        'height' => null,
                        'uploaded_by' => $note->user_id,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
