<?php

use App\Http\Controllers\WorkOrders\WorkOrderPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project work order notes page (spec 0420)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, so it inherits the same
| `auth:sanctum` group. Authorization is WorkOrderChatAccess (the chat rule),
| run by the controller in the order of the data_contract; no throttle
| (backend.md §2). scopeBindings(): `{pagePoll}` resolves only inside
| `{workOrder}` (through WorkOrder::pagePolls()).
*/

Route::scopeBindings()->prefix('work-orders/{workOrder}/page')->name('work-orders.page.')->group(function (): void {
    Route::get('/', [WorkOrderPageController::class, 'show'])->name('show');
    Route::put('/', [WorkOrderPageController::class, 'update'])->name('update');
    Route::post('polls', [WorkOrderPageController::class, 'storePoll'])->name('polls.store');
    Route::put('polls/{pagePoll}/vote', [WorkOrderPageController::class, 'vote'])->name('polls.vote');
    Route::delete('polls/{pagePoll}/vote', [WorkOrderPageController::class, 'unvote'])->name('polls.unvote');
});
