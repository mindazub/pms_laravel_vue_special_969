<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MentionSuggestionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['user', 'team', 'customer'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $queryText = (string) ($validated['q'] ?? '');

        $items = match ($validated['type']) {
            'user' => User::query()
                ->when($queryText !== '', fn ($query) => $query->where('name', 'like', $queryText.'%'))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'type' => 'User',
                ]),
            'team' => Team::query()
                ->visibleTo($request->user())
                ->when($queryText !== '', fn ($query) => $query->where('name', 'like', $queryText.'%'))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name'])
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'type' => 'Team',
                ]),
            default => Customer::query()
                ->visibleTo($request->user())
                ->when($queryText !== '', fn ($query) => $query->where('name', 'like', $queryText.'%'))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'type' => 'Customer',
                ]),
        };

        return response()->json($items->values());
    }
}
