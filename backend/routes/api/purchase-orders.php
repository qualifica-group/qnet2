<?php

use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderFromRequestLinesController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderPdfController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase orders, "ODA" (spec 0210)
|--------------------------------------------------------------------------
|
| Authorization (purchase-orders.view/create/update/delete/changeStatus/
| approve/merge, and the D-10 editable-state rule) is enforced server-side in the
| controller on every endpoint. Required from routes/api.php INSIDE the
| `auth:sanctum` group. The list is the generic `purchase-orders` table domain.
*/

Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
Route::match(['put', 'patch'], 'purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);
Route::get('purchase-orders/{purchaseOrder}/pdf', PurchaseOrderPdfController::class);
Route::post('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'status']);
Route::get('purchase-orders/{purchaseOrder}/merge-candidates', [PurchaseOrderController::class, 'mergeCandidates']);
Route::post('purchase-orders/{purchaseOrder}/merge', [PurchaseOrderController::class, 'merge']);
Route::get('purchase-orders/{purchaseOrder}/merge-logs', [PurchaseOrderController::class, 'mergeLogs']);
Route::post('purchase-request-lines/purchase-orders', [PurchaseOrderFromRequestLinesController::class, 'store']);
