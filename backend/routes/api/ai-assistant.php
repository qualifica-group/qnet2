<?php

use App\Http\Controllers\AiAssistant\AiAssistantConfigController;
use App\Http\Controllers\AiAssistant\AiAttachmentController;
use App\Http\Controllers\AiAssistant\AiConversationController;
use App\Http\Controllers\AiAssistant\AiMessageController;
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
|
*/

Route::prefix('ai-assistant')->middleware('can:'.AiConversationPolicy::PERMISSION)->group(function () {
    Route::get('config', AiAssistantConfigController::class);

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
