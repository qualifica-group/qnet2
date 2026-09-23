<?php

use App\Http\Controllers\Dashboard\DashboardTaskCountersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard (spec 0151)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6). Required from routes/api.php INSIDE the existing
| `auth:sanctum` group, so this route inherits that same middleware/prefix
| context. Authorization (`tasks.viewAny`) is enforced server-side in
| DashboardTaskCountersController via TaskPolicy — the same gate the `tasks`
| grid itself requires. No rate limiting: project rule (backend.md §2, user
| decision 2026-07-15).
*/

Route::get('dashboard/tasks', DashboardTaskCountersController::class);
