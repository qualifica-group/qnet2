<?php

use App\Http\Controllers\Treasury\AccountStatementController;
use App\Http\Controllers\Treasury\BankReconciliationController;
use App\Http\Controllers\Treasury\TreasuryOverviewController;
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

// Spec 0362: treasury overview ("Quadro finanziario"), `treasury-movements.view`.
Route::get('treasury-overview', TreasuryOverviewController::class);

// Spec 0363: ticks and bank reconciliations. Static segments before the wildcard.
Route::post('financial-accounts/{financialAccount}/reconciliation-marks', [BankReconciliationController::class, 'mark']);
Route::get('financial-accounts/{financialAccount}/bank-reconciliations/preview', [BankReconciliationController::class, 'preview']);
Route::get('financial-accounts/{financialAccount}/bank-reconciliations', [BankReconciliationController::class, 'index']);
Route::post('financial-accounts/{financialAccount}/bank-reconciliations', [BankReconciliationController::class, 'store']);
Route::delete('bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'destroy']);
