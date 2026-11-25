<?php

use App\Http\Controllers\WorkOrders\WorkOrderChatChannelController;
use App\Http\Controllers\WorkOrders\WorkOrderChatController;
use App\Http\Middleware\EnsureWorkOrderChatEnabled;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project work order chat (spec 0359)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, so it inherits the same
| `auth:sanctum` group. CHAT_ENABLED off -> 404 (EnsureWorkOrderChatEnabled,
| spec 0432). Authorization is WorkOrderChatAccess, run by the
| FormRequests in the order of the data_contract; no throttle (backend.md §2).
| scopeBindings(): `{chatMessage}` resolves only inside `{workOrder}` (through
| WorkOrder::chatMessages()) and, being soft deleted, never once deleted.
*/

Route::middleware(EnsureWorkOrderChatEnabled::class)->scopeBindings()->prefix('work-orders/{workOrder}/chat')->name('work-orders.chat.')->group(function (): void {
    Route::get('messages', [WorkOrderChatController::class, 'index'])->name('messages.index');
    Route::post('messages', [WorkOrderChatController::class, 'store'])->name('messages.store');
    Route::patch('messages/{chatMessage}', [WorkOrderChatController::class, 'update'])->name('messages.update');
    Route::delete('messages/{chatMessage}', [WorkOrderChatController::class, 'destroy'])->name('messages.destroy');
    Route::post('read', [WorkOrderChatController::class, 'read'])->name('read');
});

// The chat dock's channels (spec 0432): the actor's project work orders. Own prefix, so it never meets `{workOrder}`.
Route::get('work-order-chat/channels', [WorkOrderChatChannelController::class, 'index'])
    ->middleware(EnsureWorkOrderChatEnabled::class)
    ->name('work-order-chat.channels');
