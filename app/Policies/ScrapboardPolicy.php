<?php

namespace App\Policies;

use App\Models\Scrapboard;
use App\Models\User;

class ScrapboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Scrapboard $scrapboard): bool
    {
        return $user->isAdminOrCeo() || $scrapboard->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Scrapboard $scrapboard): bool
    {
        return $user->isAdminOrCeo() || $scrapboard->user_id === $user->id;
    }

    public function delete(User $user, Scrapboard $scrapboard): bool
    {
        return $user->isAdminOrCeo() || $scrapboard->user_id === $user->id;
    }

    public function restore(User $user, Scrapboard $scrapboard): bool
    {
        return false;
    }

    public function forceDelete(User $user, Scrapboard $scrapboard): bool
    {
        return false;
    }
}
