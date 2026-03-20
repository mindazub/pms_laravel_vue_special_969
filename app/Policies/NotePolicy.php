<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Note $note): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        if ((int) $user->id === (int) $note->user_id) {
            return true;
        }

        if ($note->assignees()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return $note->team !== null && (int) $note->team->manager_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrCeo() || $user->hasAnyRole([User::ROLE_MANAGER, User::ROLE_USER]);
    }

    public function update(User $user, Note $note): bool
    {
        return $this->view($user, $note);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->view($user, $note);
    }

    public function restore(User $user, Note $note): bool
    {
        return false;
    }

    public function forceDelete(User $user, Note $note): bool
    {
        return false;
    }
}
