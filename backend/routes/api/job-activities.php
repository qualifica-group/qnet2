<?php

use App\Http\Controllers\JobActivities\JobActivityController;
use App\Http\Controllers\JobActivities\JobActivityForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JobActivities (spec 0271)
|--------------------------------------------------------------------------
|
| Authorization (job-activities.view/create/update/delete) is enforced
| server-side in the controllers via JobActivityPolicy. Required from
| routes/api.php INSIDE the `auth:sanctum` group. `for-select` is declared
| ABOVE the `{jobActivity}` route so it is not swallowed by the binding; the list
| is the generic `job-activities` table domain.
*/

Route::get('job-activities/for-select', JobActivityForSelectController::class);
Route::get('job-activities/{jobActivity}', [JobActivityController::class, 'show']);
Route::post('job-activities', [JobActivityController::class, 'store']);
Route::match(['put', 'patch'], 'job-activities/{jobActivity}', [JobActivityController::class, 'update']);
Route::delete('job-activities/{jobActivity}', [JobActivityController::class, 'destroy']);
