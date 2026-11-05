<?php

use App\Http\Controllers\WorkOrders\WorkOrderChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project work order chat (spec 0359)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, so it inherits the same
| `auth:sanctum` group. Authorization is WorkOrderChatAccess, run by the
| FormRequests in the order of the data_contract; no throttle (backend.md §2).
| scopeBindings(): `{chatMessage}` resolves only inside `{workOrder}` (through
| WorkOrder::chatMessages()) and, being soft deleted, never once deleted.
*/

Route::scopeBindings()->prefix('work-orders/{workOrder}/chat')->name('work-orders.chat.')->group(function (): void {
    Route::get('messages', [WorkOrderChatController::class, 'index'])->name('messages.index');
    Route::post('messages', [WorkOrderChatController::class, 'store'])->name('messages.store');
    Route::patch('messages/{chatMessage}', [WorkOrderChatController::class, 'update'])->name('messages.update');
    Route::delete('messages/{chatMessage}', [WorkOrderChatController::class, 'destroy'])->name('messages.destroy');
    Route::post('read', [WorkOrderChatController::class, 'read'])->name('read');
});
