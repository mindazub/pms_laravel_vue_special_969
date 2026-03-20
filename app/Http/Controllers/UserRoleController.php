<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminOrCeo(), 403);

        $users = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_names' => $user->getRoleNames()->values()->all(),
            ])
            ->values();

        return response()->json($users);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->isAdminOrCeo(), 403);

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_CEO,
                User::ROLE_MANAGER,
                User::ROLE_HR,
                User::ROLE_USER,
            ])],
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_names' => $user->getRoleNames()->values()->all(),
        ]);
    }
}
