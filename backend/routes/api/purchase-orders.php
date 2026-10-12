<?php

use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase orders, "ODA" (spec 0210)
|--------------------------------------------------------------------------
|
| Authorization (purchase-orders.view/create/update/delete/changeStatus/
| approve, and the D-10 editable-state rule) is enforced server-side in the
| controller on every endpoint. Required from routes/api.php INSIDE the
| `auth:sanctum` group. The list is the generic `purchase-orders` table domain.
*/

Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
Route::match(['put', 'patch'], 'purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);
Route::post('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'status']);
