<?php

use App\Http\Controllers\StaffTrainings\StaffTrainingAttachmentController;
use App\Http\Controllers\StaffTrainings\StaffTrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff trainings register (spec 0284)
|--------------------------------------------------------------------------
|
| Authorization: staff-trainings.viewAny|viewTeam|viewAll|manage
| (StaffTrainingPolicy, StaffTrainingVisibility). Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group. `compliance` is declared
| ABOVE the `{staffTraining}` route so it is not swallowed by the binding; the
| list is the generic `staff-trainings` table domain.
*/

Route::get('staff-trainings/compliance', [StaffTrainingController::class, 'compliance']);
Route::get('staff-trainings/{staffTraining}', [StaffTrainingController::class, 'show']);
Route::post('staff-trainings', [StaffTrainingController::class, 'store']);
Route::match(['put', 'patch'], 'staff-trainings/{staffTraining}', [StaffTrainingController::class, 'update']);
Route::delete('staff-trainings/{staffTraining}', [StaffTrainingController::class, 'destroy']);

Route::post('staff-trainings/{staffTraining}/attachment', [StaffTrainingAttachmentController::class, 'store']);
Route::get('staff-trainings/{staffTraining}/attachment', [StaffTrainingAttachmentController::class, 'show']);
Route::delete('staff-trainings/{staffTraining}/attachment', [StaffTrainingAttachmentController::class, 'destroy']);
