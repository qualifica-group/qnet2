<?php

use App\Http\Controllers\CommissionConfigurations\CommissionConfigurationController;
use App\Http\Controllers\CommissionConfigurations\CommissionConfigurationQuoteImpactController;
use App\Http\Controllers\CommissionConfigurations\RecalculateCommissionConfigurationQuotesController;
use Illuminate\Support\Facades\Route;

Route::get('commission-configurations/{commissionConfiguration}', [CommissionConfigurationController::class, 'show']);
Route::post('commission-configurations', [CommissionConfigurationController::class, 'store']);
Route::match(['put', 'patch'], 'commission-configurations/{commissionConfiguration}', [CommissionConfigurationController::class, 'update']);
Route::delete('commission-configurations/{commissionConfiguration}', [CommissionConfigurationController::class, 'destroy']);

// Spec 0308: opt-in propagation of a saved rule to the open quotes using it.
Route::get('commission-configurations/{commissionConfiguration}/quote-impact', CommissionConfigurationQuoteImpactController::class);
Route::post('commission-configurations/{commissionConfiguration}/recalculate-quotes', RecalculateCommissionConfigurationQuotesController::class);
