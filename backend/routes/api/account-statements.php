<?php

use App\Http\Controllers\Treasury\AccountStatementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Account statements, balances and unassigned payments (spec 0360)
|--------------------------------------------------------------------------
|
| Required from WITHIN the `auth:sanctum` group of routes/api.php.
|
*/

Route::get('account-statements/balances', [AccountStatementController::class, 'balances']);
Route::get('account-statements/unassigned', [AccountStatementController::class, 'unassigned']);
Route::get('financial-accounts/{financialAccount}/statement', [AccountStatementController::class, 'statement']);
Route::get('financial-accounts/{financialAccount}/statement/export', [AccountStatementController::class, 'export']);
