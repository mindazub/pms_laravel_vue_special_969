<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeopleController extends Controller
{
    public function index(Request $request): Response
    {
        $teams = Team::query()
            ->visibleTo($request->user())
            ->with(['manager:id,name', 'customer:id,name', 'users:id,name'])
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'created_by']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('People/Index', [
            'teams' => $teams,
            'customers' => $customers,
            'users' => $users,
        ]);
    }
}
