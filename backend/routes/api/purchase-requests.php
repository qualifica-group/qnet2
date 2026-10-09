<?php

use App\Http\Controllers\PurchaseRequests\PurchaseRequestController;
use App\Http\Controllers\PurchaseRequests\PurchaseRequestLineStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase requests, "RDA" (spec 0208)
|--------------------------------------------------------------------------
|
| Authorization (purchase-requests.view/create/update/delete/close, the
| visibility scope of D-16 and the status capabilities of D-8) is enforced
| server-side in the controllers on every endpoint. Required from
| routes/api.php INSIDE the `auth:sanctum` group. The lists are the generic
| `purchase-requests` and `purchase-request-lines` table domains.
*/

Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show']);
Route::post('purchase-requests', [PurchaseRequestController::class, 'store']);
Route::match(['put', 'patch'], 'purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update']);
Route::delete('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy']);
Route::get('purchase-requests/{purchaseRequest}/closure', [PurchaseRequestController::class, 'closure']);
Route::post('purchase-requests/{purchaseRequest}/close', [PurchaseRequestController::class, 'close']);
Route::post('purchase-requests/{purchaseRequest}/notify-manager', [PurchaseRequestController::class, 'notifyManager']);

Route::post('purchase-request-lines/status', [PurchaseRequestLineStatusController::class, 'change']);
Route::get('purchase-request-lines/{purchaseRequestLine}/status-logs', [PurchaseRequestLineStatusController::class, 'logs']);
