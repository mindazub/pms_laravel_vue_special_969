<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        if ((int) $project->user_id === (int) $user->id) {
            return true;
        }

        if ($project->team_id === null) {
            return false;
        }

        return $project->team()->whereHas('users', function ($query) use ($user): void {
            $query->where('users.id', $user->id);
        })->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrCeo() || $user->hasAnyRole([User::ROLE_MANAGER, User::ROLE_USER]);
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        if ((int) $project->user_id === (int) $user->id) {
            return true;
        }

        if ((int) $project->project_manager_id === (int) $user->id) {
            return true;
        }

        return $project->team !== null && (int) $project->team->manager_id === (int) $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
