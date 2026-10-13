<?php

use App\Http\Controllers\PurchaseRequestPriorities\PurchaseRequestPriorityController;
use App\Http\Controllers\PurchaseRequestPriorities\PurchaseRequestPriorityForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase request priorities (spec 0247)
|--------------------------------------------------------------------------
|
| Lookup classifying purchase requests. Authorization
| (purchase-request-priorities.view/create/update/delete) is enforced
| server-side in PurchaseRequestPriorityController via
| PurchaseRequestPriorityPolicy. Required from routes/api/purchasing.php INSIDE
| the `auth:sanctum` group. The literal `for-select` and `reorder` segments are
| declared ABOVE the bound wildcard so they are not swallowed by it; `reorder`
| is gated on purchase-request-priorities.update in the controller.
*/

Route::get('purchase-request-priorities/for-select', PurchaseRequestPriorityForSelectController::class);
Route::post('purchase-request-priorities/reorder', [PurchaseRequestPriorityController::class, 'reorder']);
Route::get('purchase-request-priorities/{purchaseRequestPriority}', [PurchaseRequestPriorityController::class, 'show']);
Route::post('purchase-request-priorities', [PurchaseRequestPriorityController::class, 'store']);
Route::match(['put', 'patch'], 'purchase-request-priorities/{purchaseRequestPriority}', [PurchaseRequestPriorityController::class, 'update']);
Route::delete('purchase-request-priorities/{purchaseRequestPriority}', [PurchaseRequestPriorityController::class, 'destroy']);
