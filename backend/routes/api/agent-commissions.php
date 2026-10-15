<?php

use App\Http\Controllers\AgentFiscalProfiles\AgentFiscalProfileController;
use App\Http\Controllers\CommissionSettlements\CommissionAdjustmentController;
use App\Http\Controllers\CommissionSettlements\CommissionSettlementController;
use App\Http\Controllers\CommissionSettlements\CommissionSettlementRunController;
use App\Http\Controllers\CommissionSettlements\SettlementApprovalController;
use App\Http\Controllers\CommissionSettlements\SettlementDocumentController;
use App\Http\Controllers\EnasarcoParameters\EnasarcoParameterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent commissions (spec 0212, 0213)
|--------------------------------------------------------------------------
|
| Fiscal profiles of the agents. Authorization
| (agent-fiscal-profiles.view/create/update/delete) is enforced server-side in
| AgentFiscalProfileController via AgentFiscalProfilePolicy on every endpoint;
| the same holds for enasarco-parameters (EnasarcoParameterPolicy, spec 0213).
| Required from routes/api/accounting.php, itself inside the `auth:sanctum`
| group of routes/api.php, so every route below inherits that middleware.
*/

Route::get('agent-fiscal-profiles/{agentFiscalProfile}', [AgentFiscalProfileController::class, 'show']);
Route::post('agent-fiscal-profiles', [AgentFiscalProfileController::class, 'store']);
Route::match(['put', 'patch'], 'agent-fiscal-profiles/{agentFiscalProfile}', [AgentFiscalProfileController::class, 'update']);
Route::delete('agent-fiscal-profiles/{agentFiscalProfile}', [AgentFiscalProfileController::class, 'destroy']);

Route::get('enasarco-parameters/{enasarcoParameter}', [EnasarcoParameterController::class, 'show']);
Route::post('enasarco-parameters', [EnasarcoParameterController::class, 'store']);
Route::match(['put', 'patch'], 'enasarco-parameters/{enasarcoParameter}', [EnasarcoParameterController::class, 'update']);
Route::delete('enasarco-parameters/{enasarcoParameter}', [EnasarcoParameterController::class, 'destroy']);

Route::post('commission-settlement-runs', [CommissionSettlementRunController::class, 'store']);
Route::get('commission-settlement-runs/{commissionSettlementRun}', [CommissionSettlementRunController::class, 'show']);

Route::get('commission-settlements/{commissionSettlement}', [CommissionSettlementController::class, 'show']);
Route::patch('commission-settlements/{commissionSettlement}', [CommissionSettlementController::class, 'update']);
Route::delete('commission-settlements/{commissionSettlement}', [CommissionSettlementController::class, 'destroy']);
Route::post('commission-settlements/{commissionSettlement}/recalculate', [CommissionSettlementController::class, 'recalculate']);
Route::post('commission-settlements/{commissionSettlement}/issue', [CommissionSettlementController::class, 'issue']);
Route::post('commission-settlements/{commissionSettlement}/cancel', [CommissionSettlementController::class, 'cancel']);
Route::post('commission-settlements/{commissionSettlement}/document', [SettlementDocumentController::class, 'store']);
Route::delete('commission-settlements/{commissionSettlement}/document', [SettlementDocumentController::class, 'destroy']);

Route::get('commission-settlements/{commissionSettlement}/approval-draft', [SettlementApprovalController::class, 'draft']);
Route::post('commission-settlements/{commissionSettlement}/supplier', [SettlementApprovalController::class, 'supplier']);
Route::post('commission-settlements/{commissionSettlement}/approve', [SettlementApprovalController::class, 'approve']);

Route::post('commission-adjustments', [CommissionAdjustmentController::class, 'store']);
Route::put('commission-adjustments/{commissionAccrual}', [CommissionAdjustmentController::class, 'update']);
Route::delete('commission-adjustments/{commissionAccrual}', [CommissionAdjustmentController::class, 'destroy']);
