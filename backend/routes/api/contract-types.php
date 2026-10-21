<?php

use App\Http\Controllers\ContractTypes\ContractTypeController;
use App\Http\Controllers\ContractTypes\ContractTypeForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contract types catalogue (spec 0290)
|--------------------------------------------------------------------------
|
| Authorization (contract-types.view/create/update/delete) is enforced
| server-side in the controller via ContractTypePolicy. Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group. The for-select needs
| authentication only (ADR 0011); the list is the generic `contract-types`
| table domain.
*/

Route::get('contract-types/for-select', ContractTypeForSelectController::class);
Route::get('contract-types/{contractType}', [ContractTypeController::class, 'show']);
Route::post('contract-types', [ContractTypeController::class, 'store']);
Route::match(['put', 'patch'], 'contract-types/{contractType}', [ContractTypeController::class, 'update']);
Route::delete('contract-types/{contractType}', [ContractTypeController::class, 'destroy']);
