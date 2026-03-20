<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        return $team->users()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrCeo();
    }

    public function update(User $user, Team $team): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        return (int) $team->manager_id === (int) $user->id;
    }

    public function manageMembers(User $user, Team $team): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        return (int) $team->manager_id === (int) $user->id;
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    public function restore(User $user, Team $team): bool
    {
        return false;
    }

    public function forceDelete(User $user, Team $team): bool
    {
        return false;
    }
}
