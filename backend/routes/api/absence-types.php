<?php

use App\Http\Controllers\AbsenceTypes\AbsenceTypeController;
use App\Http\Controllers\AbsenceTypes\AbsenceTypeForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence types catalogue (spec 0278)
|--------------------------------------------------------------------------
|
| Authorization (absence-types.view/create/update/delete) is enforced
| server-side in the controller via AbsenceTypePolicy. Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group. The for-select needs
| authentication only (ADR 0011); the list is the generic `absence-types`
| table domain.
*/

Route::get('absence-types/for-select', AbsenceTypeForSelectController::class);
Route::get('absence-types/{absenceType}', [AbsenceTypeController::class, 'show']);
Route::post('absence-types', [AbsenceTypeController::class, 'store']);
Route::match(['put', 'patch'], 'absence-types/{absenceType}', [AbsenceTypeController::class, 'update']);
Route::delete('absence-types/{absenceType}', [AbsenceTypeController::class, 'destroy']);
