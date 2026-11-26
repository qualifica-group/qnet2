<?php

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\AiAssistant\AiTurnResource;
use App\Models\AiConversation;
use App\Models\AiTurn;
use App\Services\AiAssistant\Preview\AiPreviewDecisions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/ai-assistant/conversations/{conversation}/turns/{turn} — the state the browser
 * polls until it is final (spec 0433). Only the owner of the conversation reads it.
 */
class AiTurnController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly AiPreviewDecisions $decisions) {}

    public function show(AiConversation $conversation, AiTurn $turn): JsonResponse
    {
        try {
            $this->authorize('view', $conversation);

            // A turn waiting while its session is gone is closed here: the preview arrives as expired (AC-009).
            $this->decisions->closeIfLinkLost($turn);

            return $this->ok(new AiTurnResource($turn->fresh()->load('assistantMessage')));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['conversation' => $conversation->id, 'turn' => $turn->id]);
        }
    }
}
