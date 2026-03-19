<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AttachmentFileController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TemporaryAttachmentController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('projects', ProjectController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('notes', NoteController::class)
        ->only(['store', 'update', 'destroy']);

    Route::post('temporary-attachments', [TemporaryAttachmentController::class, 'store'])
        ->name('temporary-attachments.store');
    Route::get('temporary-attachments/{temporaryAttachment}/file', [AttachmentFileController::class, 'showTemporary'])
        ->name('temporary-attachments.show');
    Route::delete('temporary-attachments/{temporaryAttachment}', [TemporaryAttachmentController::class, 'destroy'])
        ->name('temporary-attachments.destroy');
    Route::get('attachments/{attachment}/file', [AttachmentFileController::class, 'show'])
        ->name('attachments.show');
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
