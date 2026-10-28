<?php

use App\Http\Controllers\WorkOrders\WorkOrderProgressEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Order manual progress (spec 0321)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, inside the `auth:sanctum` group.
| No throttle (backend.md §2). Authorization (WorkOrderPolicy view/update) is
| enforced in the controller; `scopeBindings()` makes a {progressEntry} of
| another commessa 404.
*/

Route::scopeBindings()->group(function (): void {
    Route::get('work-orders/{workOrder}/progress-entries', [WorkOrderProgressEntryController::class, 'index']);
    Route::post('work-orders/{workOrder}/progress-entries', [WorkOrderProgressEntryController::class, 'store']);
    Route::delete('work-orders/{workOrder}/progress-entries/{progressEntry}', [WorkOrderProgressEntryController::class, 'destroy']);
});
