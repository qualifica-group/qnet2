<?php

use App\Http\Controllers\AiAssistant\AiAssistantConfigController;
use App\Http\Controllers\AiAssistant\AiAssistantCostsController;
use App\Http\Controllers\AiAssistant\AiAssistantModelCatalogController;
use App\Http\Controllers\AiAssistant\AiAssistantSettingsController;
use App\Http\Controllers\AiAssistant\AiAttachmentController;
use App\Http\Controllers\AiAssistant\AiConversationController;
use App\Http\Controllers\AiAssistant\AiMessageController;
use App\Http\Middleware\EnsureAiAssistantEnabled;
use App\Policies\AiConversationPolicy;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI assistant (spec 0347)
|--------------------------------------------------------------------------
|
| Required from WITHIN the auth:sanctum group of routes/api.php. The whole
| prefix needs the `ai-assistant.use` permission; conversation ownership is
| enforced per record by AiConversationPolicy. No throttle (backend.md §2).
| /config stays reachable so the UI learns which surfaces are on (D-16);
| everything else answers 404 while both surfaces are off.
|
*/

// Spec 0433: settings, model lists and costs for the `super-admin` only (no assignable permission);
// they do not depend on the surface switches, so the admin can fix them while the assistant is off.
Route::prefix('ai-assistant')->middleware('super-admin')->group(function () {
    Route::get('settings', [AiAssistantSettingsController::class, 'show']);
    Route::put('settings', [AiAssistantSettingsController::class, 'update']);
    Route::get('models', [AiAssistantModelCatalogController::class, 'index']);
    Route::get('models/providers', [AiAssistantModelCatalogController::class, 'providers']);
    Route::get('costs', [AiAssistantCostsController::class, 'index']);
});

Route::prefix('ai-assistant')->middleware('can:'.AiConversationPolicy::PERMISSION)->group(function () {
    Route::get('config', AiAssistantConfigController::class);

    Route::middleware(EnsureAiAssistantEnabled::class)->group(function () {
        Route::get('conversations', [AiConversationController::class, 'index']);
        Route::post('conversations', [AiConversationController::class, 'store']);
        Route::get('conversations/{conversation}', [AiConversationController::class, 'show']);
        Route::delete('conversations/{conversation}', [AiConversationController::class, 'destroy']);
        Route::post('conversations/{conversation}/messages', [AiMessageController::class, 'store']);

        Route::scopeBindings()->get(
            'messages/{message}/attachments/{attachment}',
            [AiAttachmentController::class, 'download'],
        );
    });
});
