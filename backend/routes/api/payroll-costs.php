<?php

use App\Http\Controllers\PayrollCosts\PayrollCostImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payroll costs (spec 0430)
|--------------------------------------------------------------------------
|
| Monthly import of the labour consultant's cost file. The grid is the
| generic `payroll-costs` table. Authorization is enforced server-side by
| the request via PayrollCostPolicy. Required from routes/api/accounting.php
| INSIDE the `auth:sanctum` group.
*/

Route::post('payroll-costs/import', PayrollCostImportController::class);
