<?php

use App\Http\Controllers\WorkOrders\WorkOrderContractDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Order Contract Data (spec 0201)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, so it inherits the same
| `auth:sanctum` group. Authorization is enforced server-side
| (WorkOrderPolicy::viewContractData/managePayments); no throttle
| (backend.md §2). scopeBindings() makes {quoteLine} resolve only among the
| commessa's own lines (404 otherwise).
*/

Route::get('work-orders/{workOrder}/contract-data', [WorkOrderContractDataController::class, 'show']);
Route::patch('work-orders/{workOrder}/contract-data/lines/{quoteLine}', [WorkOrderContractDataController::class, 'updateLine'])
    ->scopeBindings();
