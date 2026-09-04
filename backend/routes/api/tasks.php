<?php

use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tasks (spec 0101)
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
