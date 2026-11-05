<?php

/*
|--------------------------------------------------------------------------
| Treasury types / financial sub-accounts (spec 0360)
|--------------------------------------------------------------------------
|
| Required from WITHIN the `auth:sanctum` group of routes/api.php.
| Static segments are declared BEFORE the {treasuryType} wildcard.
|
*/

use App\Http\Controllers\ChartOfAccounts\TreasuryTypeController;
use App\Http\Controllers\ChartOfAccounts\TreasuryTypeForSelectController;
use Illuminate\Support\Facades\Route;

Route::get('treasury-types/for-select', TreasuryTypeForSelectController::class);
Route::get('treasury-types/{treasuryType}', [TreasuryTypeController::class, 'show']);
Route::post('treasury-types', [TreasuryTypeController::class, 'store']);
Route::match(['put', 'patch'], 'treasury-types/{treasuryType}', [TreasuryTypeController::class, 'update']);
Route::delete('treasury-types/{treasuryType}', [TreasuryTypeController::class, 'destroy']);
