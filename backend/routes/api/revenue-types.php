<?php

use App\Http\Controllers\ChartOfAccounts\RevenueTypeForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Revenue sub-account picker (spec 0350, D-10)
|--------------------------------------------------------------------------
|
| Required from routes/api/accounting.php INSIDE the `auth:sanctum` group,
| ABOVE chart-of-accounts.php so the literal `for-select` segment wins over
| the bound `revenue-types/{revenueType}` wildcard.
*/

Route::get('revenue-types/for-select', RevenueTypeForSelectController::class);
