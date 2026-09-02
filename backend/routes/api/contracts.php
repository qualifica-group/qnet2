<?php

use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\ContractProgrammableLinesController;
use App\Http\Controllers\Contracts\ContractReactivationController;
use App\Http\Controllers\Contracts\ContractStatusChangeController;
use App\Http\Controllers\Contracts\ContractTerminationController;
use App\Http\Controllers\Contracts\ContractValidationController;
use App\Http\Controllers\Contracts\ContractWorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contracts (spec 0072, MT-02)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/quotes.php. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| No create/delete routes (D-6): a contract is never created or deleted by
| hand, only born/suspended by the automation on the quote's status
| transition (ContractLifecycleManager, MT-03).
*/

// Contracts show/update. Authorization (contracts.view/update) is enforced
// server-side in ContractController via ContractPolicy on every endpoint.
Route::get('contracts/{contract}', [ContractController::class, 'show']);
Route::match(['put', 'patch'], 'contracts/{contract}', [ContractController::class, 'update']);

// The 4 domain-action routes (spec 0072, BR-2/3/4, plus "Modifica stato" —
// user directive 2026-08-31 rev.2). Authorization (contracts.validate/
// terminate/reactivate/changeStatus) is enforced server-side via
// ContractPolicy on every endpoint.
Route::post('contracts/{contract}/validate', ContractValidationController::class);
Route::post('contracts/{contract}/terminate', ContractTerminationController::class);
Route::post('contracts/{contract}/reactivate', ContractReactivationController::class);
Route::post('contracts/{contract}/change-status', ContractStatusChangeController::class);

// "Programma" (spec 0095, D-1/D-2/D-6): lists the offer's REVENUE lines with
// their programming occupation, then generates ONE work order from a chosen
// group. Both routes gate on ContractPolicy::program AND
// ContractActionAvailability::mayProgram (the contract's ClosedWon-group
// lifecycle), each controller ANDing the two — see ContractActionAvailability's
// own docblock.
Route::get('contracts/{contract}/programmable-lines', ContractProgrammableLinesController::class);
Route::post('contracts/{contract}/work-orders', ContractWorkOrderController::class);
