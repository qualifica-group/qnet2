<?php

use Illuminate\Support\Facades\Route;
use Integrations\Formalab\Http\Controllers\CategorySettingsController;
use Integrations\Formalab\Http\Controllers\ClassForSelectController;
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
            Route::get('sync-state', [FormalabAdminController::class, 'syncState']);
            Route::post('sync', [FormalabAdminController::class, 'sync']);
            Route::post('test-connection', [FormalabAdminController::class, 'testConnection']);
        });

        // Options of the work order class select (spec 0426): every user editing a work order.
        Route::get('classes/for-select', ClassForSelectController::class);

        Route::get('work-orders/{workOrder}', [WorkOrderLinkController::class, 'show']);
        Route::post('work-orders/{workOrder}/sync', [WorkOrderLinkController::class, 'sync']);

        Route::get('product-categories/{productCategory}', [CategorySettingsController::class, 'show']);
        Route::put('product-categories/{productCategory}', [CategorySettingsController::class, 'update']);
    });

    // Without a configured token the endpoint does not exist.
    if ((string) config('formalab.inbound_token') !== '') {
        Route::post('webhooks/classes', [WebhookController::class, 'classes'])->middleware(InboundWebhookGuard::class);
    }
});
