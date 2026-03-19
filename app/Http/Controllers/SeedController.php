<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SeedController extends Controller
{
    public function seed()
    {
        Artisan::call('migrate:fresh --seed');

        return response()->json(['message' => 'Database seeded successfully!']);
    }
}
