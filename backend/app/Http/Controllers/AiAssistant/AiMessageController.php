<?php

namespace App\Http\Controllers\AiAssistant;

use App\Actions\AiAssistant\SendAiMessage;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\StoreAiMessageRequest;
use App\Http\Resources\AiAssistant\AiMessageResource;
use App\Http\Resources\AiAssistant\AiTurnResource;
use App\Models\AiConversation;
use App\Services\AiAssistant\Agent\AiAssistantNotConfiguredException;
use App\Services\AiAssistant\Agent\AiAssistantSwitchedOffException;
use App\Services\AiAssistant\Agent\AiTooManyActiveTurnsException;
use App\Services\AiAssistant\Agent\AiTurnAlreadyActiveException;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * POST /api/ai-assistant/conversations/{conversation}/messages — saves the message and
 * queues the turn; the answer is read by polling the turn (spec 0433, 202, no stream).
 */
class AiMessageController extends BaseApiController
{
    public function __construct(private readonly SendAiMessage $sendAiMessage) {}

    public function store(StoreAiMessageRequest $request, AiConversation $conversation): JsonResponse
    {
        try {
            $session = $request->user()->currentAccessToken();

            $turn = $this->sendAiMessage->handle(
                $request->user(),
                $session instanceof PersonalAccessToken ? $session : null,
                $conversation,
                $request->toData(),
            );

            return $this->ok([
                'user_message' => (new AiMessageResource($turn->userMessage()->with('attachments')->firstOrFail()))->resolve($request),
                'turn' => (new AiTurnResource($turn->fresh(['assistantMessage'])))->resolve($request),
            ], 'Accepted', HttpStatusEnum::ACCEPTED);
        } catch (AiAssistantSwitchedOffException) {
            return $this->fail(__('The AI assistant is switched off.'), Response::HTTP_SERVICE_UNAVAILABLE, null, ['reason' => 'switched_off']);
        } catch (AiAssistantNotConfiguredException) {
            return $this->fail(__('The AI assistant is not configured.'), Response::HTTP_SERVICE_UNAVAILABLE, null, ['reason' => 'not_configured']);
        } catch (AiTurnAlreadyActiveException) {
            return $this->fail(__('The assistant is still working on your previous message.'), HttpStatusEnum::CONFLICT->value);
        } catch (AiTooManyActiveTurnsException) {
            return $this->fail(__('You have too many assistant requests running at once. Wait for one to finish.'), HttpStatusEnum::CONFLICT->value, null, ['reason' => 'too_many_active_turns']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['conversation' => $conversation->id]);
        }
    }
}
