<?php

use App\Http\Controllers\WorkOrders\WorkOrderCostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Order Costs (spec 0190)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, so it inherits the same
| `auth:sanctum` group. Authorization is enforced server-side
| (WorkOrderPolicy::viewCosts/manageCosts); no throttle (backend.md §2).
*/

Route::get('work-orders/{workOrder}/costs', [WorkOrderCostController::class, 'show']);
Route::put('work-orders/{workOrder}/costs', [WorkOrderCostController::class, 'sync']);
