<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teams = Team::query()
            ->visibleTo($request->user())
            ->with(['manager:id,name', 'customer:id,name', 'users:id,name'])
            ->orderBy('name')
            ->get();

        return response()->json($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $managerId = (int) ($validated['manager_id'] ?? $request->user()->id);

        if (! User::query()->whereKey($managerId)->exists()) {
            $managerId = $request->user()->id;
        }

        if (isset($validated['customer_id'])) {
            $customer = Customer::query()->findOrFail($validated['customer_id']);
            $this->authorize('view', $customer);
        }

        $team = Team::query()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            'description' => $validated['description'] ?? null,
            'manager_id' => $managerId,
            'customer_id' => $validated['customer_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $memberIds = collect($validated['member_ids'] ?? [])
            ->push($managerId)
            ->push($request->user()->id)
            ->unique()
            ->values();

        $syncPayload = $memberIds
            ->mapWithKeys(function (int $userId) use ($managerId): array {
                return [$userId => ['role' => $userId === $managerId ? User::ROLE_MANAGER : User::ROLE_USER]];
            })
            ->all();

        $team->users()->sync($syncPayload);

        return response()->json($team->load(['users:id,name', 'manager:id,name', 'customer:id,name']), 201);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($team->id)],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        if (isset($validated['customer_id'])) {
            $customer = Customer::query()->findOrFail($validated['customer_id']);
            $this->authorize('view', $customer);
        }

        $managerId = $validated['manager_id'] ?? $team->manager_id;

        $team->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            'description' => $validated['description'] ?? null,
            'manager_id' => $managerId,
            'customer_id' => $validated['customer_id'] ?? null,
        ]);

        if ($managerId !== null) {
            $team->users()->syncWithoutDetaching([
                $managerId => ['role' => User::ROLE_MANAGER],
            ]);
        }

        return response()->json($team->load(['users:id,name', 'manager:id,name', 'customer:id,name']));
    }

    public function syncMembers(Request $request, Team $team): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $validated = $request->validate([
            'members' => ['required', 'array'],
            'members.*.id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role' => ['required', Rule::in(User::TEAM_SCOPED_ROLES)],
        ]);

        $syncPayload = collect($validated['members'])
            ->mapWithKeys(fn (array $member): array => [
                (int) $member['id'] => ['role' => $member['role']],
            ])
            ->all();

        if ($team->manager_id !== null) {
            $syncPayload[(int) $team->manager_id] = ['role' => User::ROLE_MANAGER];
        }

        $team->users()->sync($syncPayload);

        return response()->json($team->load(['users:id,name', 'manager:id,name', 'customer:id,name']));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);
        $team->delete();

        return response()->json(['message' => 'Team deleted.']);
    }
}
