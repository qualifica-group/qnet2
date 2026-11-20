<?php

use Illuminate\Support\Facades\Route;
use Integrations\Formalab\Http\Controllers\ClassController;
use Integrations\Formalab\Http\Controllers\FormalabAdminController;
use Integrations\Formalab\Http\Controllers\WebhookController;
use Integrations\Formalab\Http\Controllers\WorkOrderLinkController;
use Integrations\Formalab\Http\Middleware\InboundWebhookGuard;

/*
| Loaded by FormalabServiceProvider ONLY when the integration is enabled
| (spec 0422): disabled, none of these routes exists (404).
*/

Route::prefix('api/integrations/formalab')->middleware('api')->group(function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::middleware('super-admin')->group(function (): void {
            Route::get('status', [FormalabAdminController::class, 'status']);
            Route::post('sync', [FormalabAdminController::class, 'sync']);
            Route::post('test-connection', [FormalabAdminController::class, 'testConnection']);
            Route::post('link-work-orders', [FormalabAdminController::class, 'linkWorkOrders']);
        });

        Route::get('classes', [ClassController::class, 'index']);

        Route::get('work-orders/{workOrder}', [WorkOrderLinkController::class, 'show']);
        Route::put('work-orders/{workOrder}', [WorkOrderLinkController::class, 'update']);
        Route::post('work-orders/{workOrder}/sync', [WorkOrderLinkController::class, 'sync']);
        Route::delete('work-orders/{workOrder}', [WorkOrderLinkController::class, 'destroy']);
    });

    // Without a configured token the endpoint does not exist.
    if ((string) config('formalab.inbound_token') !== '') {
        Route::post('webhooks/classes', [WebhookController::class, 'classes'])->middleware(InboundWebhookGuard::class);
    }
});
