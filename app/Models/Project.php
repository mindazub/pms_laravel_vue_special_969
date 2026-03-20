<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'clipboard_text',
        'attachments',
        'mentions',
        'user_id',
        'team_id',
        'customer_id',
        'project_manager_id',
    ];

    protected $casts = [
        'attachments' => 'array',
        'mentions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdminOrCeo()) {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($user): void {
            $nested->where('user_id', $user->id)
                ->orWhereHas('team.users', function (Builder $teamUsers) use ($user): void {
                    $teamUsers->where('users.id', $user->id);
                });
        });
    }
}
