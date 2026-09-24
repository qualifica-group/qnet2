<?php

use App\Http\Controllers\Tasks\TaskApproveController;
use App\Http\Controllers\Tasks\TaskBlockController;
use App\Http\Controllers\Tasks\TaskCompleteController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskForSelectController;
use App\Http\Controllers\Tasks\TaskRejectController;
use App\Http\Controllers\Tasks\TaskRequestUpdateController;
use App\Http\Controllers\Tasks\TaskSubtaskReorderController;
use App\Http\Controllers\Tasks\TaskUnblockController;
use App\Http\Controllers\Tasks\TaskUncompleteController;
use App\Http\Controllers\TimeEntries\TaskTimeEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tasks (spec 0101, domain actions spec 0116)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/work-orders.php. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| Authorization (tasks.view/create/update/delete) is enforced server-side in
| TaskController via TaskPolicy on every endpoint, which also carries the
| D-9 visibility scoping on view/update/delete. No rate limiting anywhere:
| project rule (backend.md §2, user decision 2026-07-15).
|
| The five configurator modules (task-statuses/types/categories/priorities/
| importances) live in routes/api/lookups.php with the other lookups, not
| here.
*/

// Declared ABOVE tasks/{task} so the literal `for-select` segment wins over
// the route-model-binding wildcard (mirrors referents/for-select). The only
// gate is auth:sanctum (ADR 0011, amended 2026-07-31); the rows are still
// restricted by TaskVisibilityScope.
Route::get('tasks/for-select', TaskForSelectController::class);

Route::get('tasks/{task}', [TaskController::class, 'show']);
Route::post('tasks', [TaskController::class, 'store']);
Route::match(['put', 'patch'], 'tasks/{task}', [TaskController::class, 'update']);
Route::delete('tasks/{task}', [TaskController::class, 'destroy']);

// The 7 domain-action routes (spec 0116 D-8, spec 0118 D-10..D-14), one POST
// per row-action, modelled on routes/api/contracts.php. Each extra
// `/{task}/<verb>` segment can never collide with the wildcard above — same
// URI prefix, different segment count — so declaration order relative to it
// is not load-bearing; kept below it purely for readability (CRUD first,
// actions after). Authorization (tasks.complete/validate/block/
// requestUpdate, ANDed with the record-role matrix and the state's
// availability) is enforced server-side via TaskPolicy on every endpoint;
// App\Services\Tasks\TaskActionService re-asserts the same rules (403 D-2
// admin-as-assignee deroga, 409 is_blocked, 422 availability). No throttle
// on request-update either: rate limiting stays reserved to credential
// endpoints (backend.md §2, user decision 2026-07-15).
Route::post('tasks/{task}/complete', TaskCompleteController::class);
Route::post('tasks/{task}/uncomplete', TaskUncompleteController::class);
Route::post('tasks/{task}/approve', TaskApproveController::class);
Route::post('tasks/{task}/reject', TaskRejectController::class);
Route::post('tasks/{task}/block', TaskBlockController::class);
Route::post('tasks/{task}/unblock', TaskUnblockController::class);
Route::post('tasks/{task}/request-update', TaskRequestUpdateController::class);

// Sub-task reorder (spec 0155, D-4/D-5): `update` on the PARENT (TaskPolicy),
// never a per-child ability — dragging a row in the detail's panel is
// editing the parent's own structure, the same row `create_subtask` already
// reads off `TaskAbilityResolver::canCreateSubtask()`.
Route::post('tasks/{task}/subtasks/reorder', TaskSubtaskReorderController::class);

// Task-scoped segnatempo (spec 0122, D-9): the "Segnatempo" section of the
// Task detail. Controller lives in App\Http\Controllers\TimeEntries next to
// the rest of the module, not here — see routes/api/time-entries.php's own
// header for the reasoning. Authorization combines TaskPolicy::view (the
// D-9 visibility scope) with the `time-entries.*` permissions and
// TaskAbilityResolver::canComplete(), enforced in the controller itself.
Route::get('tasks/{task}/time-entries', [TaskTimeEntryController::class, 'index']);
Route::post('tasks/{task}/time-entries', [TaskTimeEntryController::class, 'store']);
