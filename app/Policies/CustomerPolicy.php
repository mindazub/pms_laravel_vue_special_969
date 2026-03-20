<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->isAdminOrCeo()) {
            return true;
        }

        return $customer->teams()->whereHas('users', function ($query) use ($user): void {
            $query->where('users.id', $user->id);
        })->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrCeo();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isAdminOrCeo();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isAdminOrCeo();
    }

    public function restore(User $user, Customer $customer): bool
    {
        return false;
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
