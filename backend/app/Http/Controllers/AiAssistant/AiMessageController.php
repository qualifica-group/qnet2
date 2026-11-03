<?php

namespace App\Http\Controllers\AiAssistant;

use App\Actions\AiAssistant\SendAiMessage;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Services\AiAssistant\Drivers\AiAssistantNotConfiguredException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * POST /api/ai-assistant/conversations/{conversation}/messages — sends a turn
 * and streams the answer as server-sent events (spec 0347, D-8).
 */
class AiMessageController extends BaseApiController
{
    public function __construct(private readonly SendAiMessage $sendAiMessage) {}

    public function store(StoreAiMessageRequest $request, AiConversation $conversation): StreamedResponse|JsonResponse
    {
        try {
            return $this->sendAiMessage->handle($request->user(), $conversation, $request->toData());
        } catch (AiAssistantNotConfiguredException) {
            return $this->fail(__('The AI assistant is not configured.'), Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['conversation' => $conversation->id]);
        }
    }
}
