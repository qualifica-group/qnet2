<?php

use App\Http\Controllers\CostTypes\CostTypeController;
use App\Http\Controllers\CostTypes\CostTypeForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cost types (spec 0210)
|--------------------------------------------------------------------------
|
| Lookup classifying purchase order lines. Authorization
| (cost-types.view/create/update/delete) is enforced server-side in
| CostTypeController via CostTypePolicy. Required from routes/api.php INSIDE
| the `auth:sanctum` group. `for-select` is declared ABOVE the `{costType}`
| route so it is not swallowed by the binding; the list is the generic
| `cost-types` table domain.
*/

Route::get('cost-types/for-select', CostTypeForSelectController::class);
Route::get('cost-types/{costType}', [CostTypeController::class, 'show']);
Route::post('cost-types', [CostTypeController::class, 'store']);
Route::match(['put', 'patch'], 'cost-types/{costType}', [CostTypeController::class, 'update']);
Route::delete('cost-types/{costType}', [CostTypeController::class, 'destroy']);
