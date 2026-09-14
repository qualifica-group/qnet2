<?php

use App\Http\Controllers\TaskTemplates\TaskTemplateController;
use App\Http\Controllers\TaskTemplates\TaskTemplateForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Task templates / "Modelli di Task" (spec 0124)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/tasks.php. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| Declared ABOVE task-templates/{taskTemplate} so the literal `for-select`
| segment wins over the route-model-binding wildcard (mirrors
| tasks/for-select). Authorization (task-templates.view/create/update/
| delete) is enforced server-side in TaskTemplateController via
| TaskTemplatePolicy; for-select has no gate beyond auth:sanctum (D-8). No
| rate limiting: project rule (backend.md §2, user decision 2026-07-15).
|
| A template's rows have no endpoint of their own (D-1): they are written
| only inside POST/PUT/PATCH below, as the `items` array.
*/

Route::get('task-templates/for-select', TaskTemplateForSelectController::class);

Route::get('task-templates/{taskTemplate}', [TaskTemplateController::class, 'show']);
Route::post('task-templates', [TaskTemplateController::class, 'store']);
Route::match(['put', 'patch'], 'task-templates/{taskTemplate}', [TaskTemplateController::class, 'update']);
Route::delete('task-templates/{taskTemplate}', [TaskTemplateController::class, 'destroy']);
