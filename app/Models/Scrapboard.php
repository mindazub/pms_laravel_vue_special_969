<?php

namespace App\Models;

use Database\Factories\ScrapboardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scrapboard extends Model
{
    /** @use HasFactory<ScrapboardFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'workbook_data',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdminOrCeo()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    protected function casts(): array
    {
        return [
            'workbook_data' => 'array',
        ];
    }
}
