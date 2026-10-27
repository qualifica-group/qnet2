<?php

/*
|--------------------------------------------------------------------------
| Workspaces / "App" (spec 0317)
|--------------------------------------------------------------------------
|
| Required from WITHIN the `auth:sanctum` group of routes/api.php.
| Static segments are declared BEFORE the {workspace} wildcard.
|
*/

use App\Http\Controllers\Workspaces\AvailableWorkspacesController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Any authenticated user: the workspaces they can enter.
Route::get('workspaces/available', AvailableWorkspacesController::class);

Route::middleware('super-admin')->group(function () {
    Route::get('workspaces/navigation-catalogue', [WorkspaceController::class, 'catalogue']);
    Route::apiResource('workspaces', WorkspaceController::class);
});
