<?php

use App\Http\Controllers\NoteController;
use App\Http\Controllers\SeedController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('notes', NoteController::class)
    ->names('api.notes')
    ->middleware('auth:sanctum');
Route::post('/seed', [SeedController::class, 'seed'])->middleware('auth:sanctum');
