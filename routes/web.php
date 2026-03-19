<?php

use App\Http\Controllers\Dashboard2025Controller;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Projects2025Controller;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard-2025', Dashboard2025Controller::class)->name('dashboard.2025');

    Route::resource('projects', ProjectController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/projects-2025', [Projects2025Controller::class, 'index'])->name('projects.2025');
    Route::post('/projects-2025/import', [Projects2025Controller::class, 'import'])->name('projects.2025.import');

    Route::resource('notes', NoteController::class)
        ->only(['store', 'update', 'destroy']);
});

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
