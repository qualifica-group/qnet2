<?php

use App\Http\Controllers\TimeEntries\TimeEntryController;
use App\Http\Controllers\TimeEntries\TimeEntryDayNoteController;
use App\Http\Controllers\TimeEntries\TimeEntryExportController;
use App\Http\Controllers\TimeEntries\TimeEntryStatsController;
use App\Http\Controllers\TimeEntries\TimeEntryTeamStatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Time entries / segnatempo (spec 0122, MT-B2/MT-B3)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/tasks.php. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| `time-entries/day-notes` and `time-entries/stats/*` are declared ABOVE
| `time-entries/{timeEntry}` so the literal segments win over the
| route-model-binding wildcard (mirrors `tasks/for-select` above
| `tasks/{task}`) — stats/* would not actually collide (three segments vs
| two), but the ordering stays consistent with day-notes' for readability.
|
| Authorization is enforced server-side in the controllers via
| TimeEntryPolicy (record-level view/update/delete), rule R
| (TimeEntryReadAuthorizer, inside TimeEntryListService/TimeEntryStatsService
| for index/stats/*) plus a handful of resource-level `time-entries.*`
| permission checks that have no model to authorize against (create,
| manageAll on someone else's `user_id`) — see
| TimeEntryController/TimeEntryDayNoteController. No rate limiting: project
| rule (backend.md §2, user decision 2026-07-15).
|
| The export endpoints (data_contract) are out of MT-B3's scope — later
| microtasks (MT-B4/B5) add them here.
|
| The two Task-scoped routes (`GET|POST /api/tasks/{task}/time-entries`,
| D-9) live in routes/api/tasks.php instead, next to the rest of the Task
| module's routes.
*/

Route::put('time-entries/day-notes', TimeEntryDayNoteController::class);
Route::get('time-entries/stats/overview', [TimeEntryStatsController::class, 'overview']);
Route::get('time-entries/stats/pulse', [TimeEntryStatsController::class, 'pulse']);
Route::get('time-entries/stats/team', TimeEntryTeamStatsController::class);
Route::get('time-entries/exports/filtered', [TimeEntryExportController::class, 'filtered']);
Route::get('time-entries/exports/monthly', [TimeEntryExportController::class, 'monthly']);

Route::get('time-entries', [TimeEntryController::class, 'index']);
Route::post('time-entries', [TimeEntryController::class, 'store']);
Route::get('time-entries/{timeEntry}', [TimeEntryController::class, 'show']);
Route::put('time-entries/{timeEntry}', [TimeEntryController::class, 'update']);
Route::delete('time-entries/{timeEntry}', [TimeEntryController::class, 'destroy']);
