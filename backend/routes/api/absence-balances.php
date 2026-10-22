<?php

use App\Http\Controllers\AbsenceBalances\AbsenceAllowanceController;
use App\Http\Controllers\AbsenceBalances\AbsenceBalanceController;
use App\Http\Controllers\AbsenceBalances\AbsenceBalanceOverviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence balances and allowances (specs 0278, 0304)
|--------------------------------------------------------------------------
|
| Authorization: absence-requests.viewAny (balances, plus visibility of the
| person) / absence-requests.manageAll (allowances).
| Required from routes/api/hr.php INSIDE the `auth:sanctum` group.
*/

Route::get('absence-balances', [AbsenceBalanceController::class, 'index']);
Route::put('absence-allowances', [AbsenceAllowanceController::class, 'upsert']);
Route::put('absence-allowances/bulk', [AbsenceAllowanceController::class, 'bulk']);
Route::get('absence-balances/overview', [AbsenceBalanceOverviewController::class, 'index']);
Route::get('absence-balances/overview/export', [AbsenceBalanceOverviewController::class, 'export']);
