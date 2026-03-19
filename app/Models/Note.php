<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    public const SCOPE_GENERAL = 'general';

    public const SCOPE_WORKLOAD_2025 = 'workload_2025';

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
    ];

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'content',
        'clipboard_text',
        'status',
        'progress',
        'attachments',
        'scope',
        'work_year',
        'work_week_label',
        'work_week_number',
        'owner_name_raw',
        'category',
        'linked_goal',
        'priority_code',
        'workload_status',
        'estimated_time_hours',
        'moved_to_next_week',
        'replaced_task',
        'source_date_added',
        'import_batch',
        'import_fingerprint',
    ];

    protected $casts = [
        'progress' => 'integer',
        'attachments' => 'array',
        'work_year' => 'integer',
        'work_week_number' => 'integer',
        'estimated_time_hours' => 'float',
        'moved_to_next_week' => 'boolean',
        'source_date_added' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeWorkload2025(Builder $query): Builder
    {
        return $query
            ->where('scope', self::SCOPE_WORKLOAD_2025)
            ->where('work_year', 2025);
    }
}
