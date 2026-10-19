<?php

use App\Http\Controllers\JobRoles\JobRoleController;
use App\Http\Controllers\JobRoles\JobRoleForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JobRoles (spec 0271)
|--------------------------------------------------------------------------
|
| Authorization (job-roles.view/create/update/delete) is enforced
| server-side in the controllers via JobRolePolicy. Required from
| routes/api.php INSIDE the `auth:sanctum` group. `for-select` is declared
| ABOVE the `{jobRole}` route so it is not swallowed by the binding; the list
| is the generic `job-roles` table domain.
*/

Route::get('job-roles/for-select', JobRoleForSelectController::class);
Route::get('job-roles/{jobRole}', [JobRoleController::class, 'show']);
Route::post('job-roles', [JobRoleController::class, 'store']);
Route::match(['put', 'patch'], 'job-roles/{jobRole}', [JobRoleController::class, 'update']);
Route::delete('job-roles/{jobRole}', [JobRoleController::class, 'destroy']);
