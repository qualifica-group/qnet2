<?php

use App\Http\Controllers\CommissionConfigurations\CommissionConfigurationController;
use Illuminate\Support\Facades\Route;

Route::get('commission-configurations/{commissionConfiguration}', [CommissionConfigurationController::class, 'show']);
Route::post('commission-configurations', [CommissionConfigurationController::class, 'store']);
Route::match(['put', 'patch'], 'commission-configurations/{commissionConfiguration}', [CommissionConfigurationController::class, 'update']);
Route::delete('commission-configurations/{commissionConfiguration}', [CommissionConfigurationController::class, 'destroy']);
