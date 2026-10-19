<?php

use App\Http\Controllers\CollectiveAgreements\CollectiveAgreementController;
use App\Http\Controllers\CollectiveAgreements\CollectiveAgreementLevelForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Collective agreements - CCNL (spec 0272)
|--------------------------------------------------------------------------
|
| Authorization (collective-agreements.view/create/update/delete) is enforced
| server-side in the controller via CollectiveAgreementPolicy. Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group. The levels for-select
| needs authentication only (ADR 0011); the list is the generic
| `collective-agreements` table domain.
*/

Route::get('collective-agreement-levels/for-select', CollectiveAgreementLevelForSelectController::class);
Route::get('collective-agreements/{collectiveAgreement}', [CollectiveAgreementController::class, 'show']);
Route::post('collective-agreements', [CollectiveAgreementController::class, 'store']);
Route::match(['put', 'patch'], 'collective-agreements/{collectiveAgreement}', [CollectiveAgreementController::class, 'update']);
Route::delete('collective-agreements/{collectiveAgreement}', [CollectiveAgreementController::class, 'destroy']);
