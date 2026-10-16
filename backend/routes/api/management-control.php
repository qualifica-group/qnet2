<?php

use App\Http\Controllers\ManagementControl\CashFlowController;
use App\Http\Controllers\ManagementControl\ExposureController;
use App\Http\Controllers\ManagementControl\IncomeStatementController;
use App\Http\Controllers\ManagementControl\MonthlyTrendController;
use App\Http\Controllers\ManagementControl\StructureCostsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Management control (spec 0268, "Controllo di gestione")
|--------------------------------------------------------------------------
|
| Read-only reports gated by `management-control.view` in the FormRequests.
| Required from routes/api.php INSIDE the existing `auth:sanctum` group.
*/

Route::prefix('management-control')->group(function () {
    Route::get('income-statement', IncomeStatementController::class);
    Route::get('structure-costs', StructureCostsController::class);
    Route::get('exposure', ExposureController::class);
    Route::get('cash-flow', CashFlowController::class);
    Route::get('monthly-trend', MonthlyTrendController::class);
});
