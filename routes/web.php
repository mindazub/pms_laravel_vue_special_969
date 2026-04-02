<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Dashboard2025Controller;
use App\Http\Controllers\MentionSuggestionController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Projects2025Controller;
use App\Http\Controllers\ScrapboardController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard-2025', Dashboard2025Controller::class)->name('dashboard.2025');

    Route::resource('projects', ProjectController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::prefix('people')->name('people.')->group(function () {
        Route::get('/', [PeopleController::class, 'index'])->name('index');
        Route::get('/teams', [PeopleController::class, 'teams'])->name('teams.index');
        Route::get('/customers', [PeopleController::class, 'customers'])->name('customers.index');
        Route::get('/users', [PeopleController::class, 'users'])->name('users.index');
    });

    Route::resource('teams', TeamController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::patch('/teams/{team}/members', [TeamController::class, 'syncMembers'])
        ->name('teams.members.sync');

    Route::resource('customers', CustomerController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/mentions/suggestions', MentionSuggestionController::class)
        ->name('mentions.suggestions');

    Route::get('/scrapboards', [ScrapboardController::class, 'index'])
        ->name('scrapboards.index');

    Route::get('/scrapboards/{scrapboard}', [ScrapboardController::class, 'show'])
        ->name('scrapboards.show');

    Route::post('/scrapboards', [ScrapboardController::class, 'store'])
        ->name('scrapboards.store');

    Route::put('/scrapboards/{scrapboard}', [ScrapboardController::class, 'update'])
        ->name('scrapboards.update');

    Route::delete('/scrapboards/{scrapboard}', [ScrapboardController::class, 'destroy'])
        ->name('scrapboards.destroy');

    Route::get('/users/roles', [UserRoleController::class, 'index'])
        ->name('users.roles.index');

    Route::put('/users/{user}/role', [UserRoleController::class, 'update'])
        ->name('users.roles.update');

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
