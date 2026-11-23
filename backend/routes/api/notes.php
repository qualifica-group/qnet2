<?php

use App\Http\Controllers\Notes\MentionableUserController;
use App\Http\Controllers\Notes\NoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notes — agnostic collaborative discussion component (spec 0052)
|--------------------------------------------------------------------------
|
| Required from routes/api.php INSIDE the existing `auth:sanctum` group, so
| every route here inherits that middleware. `mentionable-users` is declared
| BEFORE `{note}` so it is never captured by the note route-model binding.
| No `throttle` (decision 2026-07-15: rate limiting is reserved for
| credential endpoints).
*/
Route::get('notes/mentionable-users', [MentionableUserController::class, 'index']);
Route::get('notes', [NoteController::class, 'index']);
Route::post('notes', [NoteController::class, 'store']);
Route::post('notes/read', [NoteController::class, 'markRead']);
Route::patch('notes/{note}', [NoteController::class, 'update']);
Route::delete('notes/{note}', [NoteController::class, 'destroy']);
