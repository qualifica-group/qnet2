<?php

use App\Http\Controllers\Stock\StockBalanceController;
use App\Http\Controllers\Stock\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stock (equipment) routes (spec 0307)
|--------------------------------------------------------------------------
|
| Authorization: stock.viewAny (reads), stock.manage (record a movement),
| both checked by the FormRequests (StockPolicy). Movements are append-only:
| no update or delete route. Required from routes/api/hr.php.
|
*/

Route::get('stock-balances', [StockBalanceController::class, 'index']);
Route::get('stock-movements', [StockMovementController::class, 'index']);
Route::post('stock-movements', [StockMovementController::class, 'store']);
