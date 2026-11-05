<?php

/*
|--------------------------------------------------------------------------
| Treasury movements (spec 0355)
|--------------------------------------------------------------------------
|
| Required from WITHIN the `auth:sanctum` group of routes/api.php.
| Static segments are declared BEFORE the {treasuryMovement} wildcard.
|
*/

use App\Http\Controllers\Treasury\TreasuryMovementController;
use Illuminate\Support\Facades\Route;

Route::post('treasury-movements', [TreasuryMovementController::class, 'store']);
Route::get('treasury-movements/{treasuryMovement}', [TreasuryMovementController::class, 'show']);
Route::match(['put', 'patch'], 'treasury-movements/{treasuryMovement}', [TreasuryMovementController::class, 'update']);
Route::delete('treasury-movements/{treasuryMovement}', [TreasuryMovementController::class, 'destroy']);
