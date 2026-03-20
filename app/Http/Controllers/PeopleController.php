<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeopleController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $visibleUsers = $this->visibleUsersQuery($viewer, true)->get();
        $visibleTeams = Team::query()
            ->visibleTo($viewer)
            ->with(['manager:id,name', 'customer:id,name', 'users:id,name'])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $visibleCustomers = Customer::query()
            ->visibleTo($viewer)
            ->withCount('teams')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'created_by']);

        $assignedUsersCount = $visibleUsers->filter(fn (User $user): bool => $user->teams->isNotEmpty())->count();
        $totalUsersCount = $visibleUsers->count();
        $coverageRate = $totalUsersCount === 0
            ? 0
            : (int) round(($assignedUsersCount / $totalUsersCount) * 100);

        $roleDistribution = collect([
            User::ROLE_ADMIN,
            User::ROLE_CEO,
            User::ROLE_MANAGER,
            User::ROLE_HR,
            User::ROLE_USER,
        ])->map(fn (string $role): array => [
            'role' => $role,
            'count' => $visibleUsers->filter(fn (User $user): bool => $user->hasRole($role))->count(),
        ])->values();

        $largestTeams = $visibleTeams
            ->sortByDesc('users_count')
            ->take(5)
            ->values()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'users_count' => (int) $team->users_count,
                'manager_name' => $team->manager?->name,
                'customer_name' => $team->customer?->name,
            ]);

        $usersWithoutTeams = $visibleUsers
            ->filter(fn (User $user): bool => $user->teams->isEmpty())
            ->take(6)
            ->values()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return Inertia::render('People/Index', [
            'metrics' => [
                'total_users' => $totalUsersCount,
                'total_teams' => $visibleTeams->count(),
                'total_customers' => $visibleCustomers->count(),
                'manager_count' => $visibleUsers->filter(fn (User $user): bool => $user->hasRole(User::ROLE_MANAGER))->count(),
                'hr_count' => $visibleUsers->filter(fn (User $user): bool => $user->hasRole(User::ROLE_HR))->count(),
                'assigned_users_count' => $assignedUsersCount,
                'unassigned_users_count' => $usersWithoutTeams->count(),
                'coverage_rate' => $coverageRate,
            ],
            'largestTeams' => $largestTeams,
            'roleDistribution' => $roleDistribution,
            'usersWithoutTeams' => $usersWithoutTeams,
            'recentCustomers' => $visibleCustomers
                ->take(5)
                ->values()
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'teams_count' => (int) $customer->teams_count,
                    'description' => $customer->description,
                ]),
        ]);
    }

    public function teams(Request $request): Response
    {
        $viewer = $request->user();

        return Inertia::render('People/Teams', [
            'teams' => Team::query()
                ->visibleTo($viewer)
                ->with(['manager:id,name', 'customer:id,name', 'users:id,name'])
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'customers' => Customer::query()
                ->visibleTo($viewer)
                ->orderBy('name')
                ->get(['id', 'name']),
            'users' => $this->visibleUsersQuery($viewer)
                ->get(['users.id', 'users.name']),
        ]);
    }

    public function customers(Request $request): Response
    {
        $viewer = $request->user();

        return Inertia::render('People/Customers', [
            'customers' => Customer::query()
                ->visibleTo($viewer)
                ->withCount('teams')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'created_by']),
        ]);
    }

    public function users(Request $request): Response
    {
        $viewer = $request->user();

        $users = $this->visibleUsersQuery($viewer, true)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_names' => $user->getRoleNames()->values()->all(),
                'team_names' => $user->teams->pluck('name')->sort()->values()->all(),
                'team_count' => $user->teams->count(),
            ])
            ->values();

        return Inertia::render('People/Users', [
            'users' => $users,
            'canManageRoles' => $viewer->isAdminOrCeo(),
        ]);
    }

    private function visibleUsersQuery(User $user, bool $withTeams = false): Builder
    {
        $query = User::query()
            ->select('users.*')
            ->orderBy('name');

        if ($withTeams) {
            $query->with('teams:id,name');
        }

        if ($user->isAdminOrCeo()) {
            return $query;
        }

        return $query
            ->where(function (Builder $nested) use ($user): void {
                $nested->whereKey($user->id)
                    ->orWhereHas('teams.users', function (Builder $teamUsers) use ($user): void {
                        $teamUsers->where('users.id', $user->id);
                    });
            })
            ->distinct();
    }
}
