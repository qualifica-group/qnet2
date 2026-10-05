<?php

use App\Http\Controllers\FinancialAccounts\FinancialAccountController;
use App\Http\Controllers\FinancialAccounts\FinancialAccountForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Financial accounts (spec 0189)
|--------------------------------------------------------------------------
|
| Bank accounts, cards and cash boxes. Authorization
| (financial-accounts.view/create/update/delete/revealCardNumber) is enforced
| server-side in FinancialAccountController via FinancialAccountPolicy on every
| endpoint. Required from routes/api.php INSIDE the `auth:sanctum` group, so
| every route below inherits that middleware. `for-select` is declared ABOVE
| the bound wildcard so the literal segment wins; its only gate is auth:sanctum
| (ADR 0011, amended 2026-07-31).
*/

Route::get('financial-accounts/for-select', FinancialAccountForSelectController::class);

Route::get('financial-accounts/{financialAccount}', [FinancialAccountController::class, 'show']);
Route::get('financial-accounts/{financialAccount}/card-number', [FinancialAccountController::class, 'revealCardNumber']);
Route::post('financial-accounts', [FinancialAccountController::class, 'store']);
Route::match(['put', 'patch'], 'financial-accounts/{financialAccount}', [FinancialAccountController::class, 'update']);
Route::delete('financial-accounts/{financialAccount}', [FinancialAccountController::class, 'destroy']);
