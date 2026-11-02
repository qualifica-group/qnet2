<?php

use App\Http\Controllers\CategoryTabs\GridCategoryTabPreferencesController;
use App\Http\Controllers\CategoryTabs\GridCategoryTabsController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\ContractMultiProgrammableLinesController;
use App\Http\Controllers\Contracts\ContractMultiQuoteWorkOrderController;
use App\Http\Controllers\Contracts\ContractProgrammableLinesController;
use App\Http\Controllers\Contracts\ContractReactivationController;
use App\Http\Controllers\Contracts\ContractStatusChangeController;
use App\Http\Controllers\Contracts\ContractTerminationController;
use App\Http\Controllers\Contracts\ContractValidationController;
use App\Http\Controllers\Contracts\ContractWorkOrderBatchController;
use App\Http\Controllers\Contracts\ContractWorkOrderController;
use App\Tables\CategoryTabs\CategoryTabGrid;
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

// "Programma commessa unica" (spec 0267, D-3): static paths BEFORE
// `contracts/{contract}` so they never reach model binding. Both routes gate
// every selected contract on `contracts.program` AND ClosedWon.
Route::get('contracts/multi-programmable-lines', ContractMultiProgrammableLinesController::class);
Route::post('contracts/multi-quote-work-orders', ContractMultiQuoteWorkOrderController::class);

// Spec 0335 (D-8): the category tab strip of the grid (counts + the actor's
// favourites). Declared BEFORE the wildcard, otherwise "product-categories"
// is swallowed by its route model binding.
Route::get('contracts/product-categories', GridCategoryTabsController::class)
    ->defaults(CategoryTabGrid::ROUTE_DEFAULT, CategoryTabGrid::Contracts->value);
Route::get('contracts/category-tab-preferences', [GridCategoryTabPreferencesController::class, 'show'])
    ->defaults(CategoryTabGrid::ROUTE_DEFAULT, CategoryTabGrid::Contracts->value);
Route::put('contracts/category-tab-preferences', [GridCategoryTabPreferencesController::class, 'update'])
    ->defaults(CategoryTabGrid::ROUTE_DEFAULT, CategoryTabGrid::Contracts->value);

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

// Spec 0215: several commesse in one all-or-nothing request (one group each).
Route::post('contracts/{contract}/work-orders/batch', ContractWorkOrderBatchController::class);
