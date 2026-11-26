<?php

namespace App\Http\Controllers\AiAssistant;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\AiPreviewDecisionRequest;
use App\Http\Resources\AiAssistant\AiTurnResource;
use App\Models\AiConversation;
use App\Models\AiTurn;
use App\Services\AiAssistant\Agent\ConnectorUnavailableException;
use App\Services\AiAssistant\Preview\AiPreviewDecisionException;
use App\Services\AiAssistant\Preview\AiPreviewDecisions;
use App\Services\McpLinks\AssistantClientNotConfiguredException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * POST .../turns/{turn}/confirm and .../cancel — the buttons of the preview card (spec 0433, D-3).
 * Only the owner of the conversation decides; the preview must be the open one of THIS turn.
 * Confirmation goes through the confirmation route of the connector, never through a tool.
 */
class AiPreviewController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly AiPreviewDecisions $decisions) {}

    public function confirm(AiPreviewDecisionRequest $request, AiConversation $conversation, AiTurn $turn): JsonResponse
    {
        return $this->decide($request, $conversation, $turn, __FUNCTION__);
    }

    public function cancel(AiPreviewDecisionRequest $request, AiConversation $conversation, AiTurn $turn): JsonResponse
    {
        return $this->decide($request, $conversation, $turn, __FUNCTION__);
    }

    private function decide(AiPreviewDecisionRequest $request, AiConversation $conversation, AiTurn $turn, string $action): JsonResponse
    {
        try {
            $this->authorize('view', $conversation);

            $previewId = (string) $request->validated('preview_id');
            $decided = $action === 'confirm' ? $this->decisions->confirm($turn, $previewId) : $this->decisions->cancel($turn, $previewId);

            return $this->ok(new AiTurnResource($decided->load('assistantMessage')), 'Accepted', HttpStatusEnum::ACCEPTED);
        } catch (AiPreviewDecisionException $exception) {
            return $exception->reason === AiPreviewDecisionException::NOT_FOUND
                ? $this->fail(__('Preview not found.'), Response::HTTP_NOT_FOUND)
                : $this->fail(__($exception->reason === AiPreviewDecisionException::NOT_CONFIRMABLE ? 'This preview cannot be confirmed.' : 'This preview is no longer open.'), HttpStatusEnum::CONFLICT->value, null, ['reason' => $exception->reason]);
        } catch (ConnectorUnavailableException|AssistantClientNotConfiguredException) {
            // The preview stays open: nothing was confirmed that we know of, and nothing is repeated.
            return $this->fail(__('The assistant could not reach its tools. Try again later.'), Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, $action, ['conversation' => $conversation->id, 'turn' => $turn->id]);
        }
    }
}
