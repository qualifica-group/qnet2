<?php

use App\Http\Controllers\ProformaRequests\ProformaRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Proforma requests (spec 0193)
|--------------------------------------------------------------------------
|
| Authorization (proforma-requests.view/create/update/delete, plus
| WorkOrderPolicy::view on the work-order-scoped routes) is enforced
| server-side in ProformaRequestController on every endpoint. Required from
| routes/api.php INSIDE the `auth:sanctum` group. The list is the generic
| `proforma-requests` table domain (no index route here).
*/

Route::scopeBindings()->group(function () {
    Route::get('work-orders/{workOrder}/proforma-requests/summary', [ProformaRequestController::class, 'summary']);
    Route::post('work-orders/{workOrder}/proforma-requests', [ProformaRequestController::class, 'store']);
});

Route::get('proforma-requests/{proformaRequest}', [ProformaRequestController::class, 'show']);
Route::patch('proforma-requests/{proformaRequest}', [ProformaRequestController::class, 'update']);
Route::delete('proforma-requests/{proformaRequest}', [ProformaRequestController::class, 'destroy']);
