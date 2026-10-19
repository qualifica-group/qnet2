<?php

use App\Http\Controllers\AbsenceBalances\AbsenceAllowanceController;
use App\Http\Controllers\AbsenceBalances\AbsenceBalanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence balances and allowances (spec 0278)
|--------------------------------------------------------------------------
|
| Authorization: absence-requests.viewAny (balances, plus visibility of the
| person) / absence-requests.manageAll (allowances).
| Required from routes/api/hr.php INSIDE the `auth:sanctum` group.
*/

Route::get('absence-balances', [AbsenceBalanceController::class, 'index']);
Route::put('absence-allowances', [AbsenceAllowanceController::class, 'upsert']);
