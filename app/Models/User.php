<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const ROLE_ADMIN = 'Admin';

    public const ROLE_CEO = 'CEO';

    public const ROLE_MANAGER = 'Manager';

    public const ROLE_HR = 'HR';

    public const ROLE_USER = 'User';

    public const TEAM_SCOPED_ROLES = [
        self::ROLE_MANAGER,
        self::ROLE_HR,
        self::ROLE_USER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    public function managedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedNotes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'note_user_assignees')
            ->withTimestamps();
    }

    public function isAdminOrCeo(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_CEO]);
    }

    public function hasTeamRole(Team $team, string $role): bool
    {
        if ($this->isAdminOrCeo()) {
            return true;
        }

        return $this->teams()
            ->whereKey($team->id)
            ->wherePivot('role', $role)
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
