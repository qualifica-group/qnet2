<?php

use App\Http\Controllers\AiAssistant\AiAssistantConfigController;
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
