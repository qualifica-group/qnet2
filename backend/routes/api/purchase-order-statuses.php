<?php

use App\Http\Controllers\PurchaseOrderStatuses\PurchaseOrderStatusController;
use App\Http\Controllers\PurchaseOrderStatuses\PurchaseOrderStatusForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase order status presentation catalogue (spec 0248)
|--------------------------------------------------------------------------
|
| System rows (one per PurchaseOrderStatus case): no create, no delete.
| Authorization (purchase-order-statuses.view/update) is enforced in
| PurchaseOrderStatusController via PurchaseOrderStatusLabelPolicy. Required
| from routes/api/purchasing.php INSIDE the `auth:sanctum` group. The literal
| `for-select` and `reorder` segments sit ABOVE the bound wildcard.
*/

Route::get('purchase-order-statuses/for-select', PurchaseOrderStatusForSelectController::class);
Route::post('purchase-order-statuses/reorder', [PurchaseOrderStatusController::class, 'reorder']);
Route::get('purchase-order-statuses/{purchaseOrderStatus}', [PurchaseOrderStatusController::class, 'show']);
Route::match(['put', 'patch'], 'purchase-order-statuses/{purchaseOrderStatus}', [PurchaseOrderStatusController::class, 'update']);
