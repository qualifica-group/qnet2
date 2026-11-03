<?php

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\IndexAiConversationRequest;
use App\Http\Resources\AiAssistant\AiConversationResource;
use App\Models\AiConversation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Conversations of the AI assistant (spec 0347): always scoped to the
 * authenticated user, ownership enforced by AiConversationPolicy.
 */
class AiConversationController extends BaseApiController
{
    use AuthorizesRequests;

    public function index(IndexAiConversationRequest $request): JsonResponse
    {
        try {
            $offset = (int) $request->validated('offset', 0);
            $limit = (int) $request->validated('limit', IndexAiConversationRequest::DEFAULT_LIMIT);

            $query = AiConversation::query()->where('user_id', $request->user()->id);
            $total = (clone $query)->count();

            // Conversations never answered (null last_message_at) go last.
            $items = $query
                ->orderByRaw('last_message_at IS NULL')
                ->orderByDesc('last_message_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return $this->paginatedResponse(AiConversationResource::collection($items), $total, $offset, $limit);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', AiConversation::class);

            $conversation = AiConversation::query()->create([
                'user_id' => $request->user()->id,
                'title' => '',
            ]);

            return $this->created(new AiConversationResource($conversation));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function show(AiConversation $conversation): JsonResponse
    {
        try {
            $this->authorize('view', $conversation);

            return $this->ok(new AiConversationResource($conversation->load('messages.attachments')));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['conversation' => $conversation->id]);
        }
    }

    public function destroy(AiConversation $conversation): JsonResponse
    {
        try {
            $this->authorize('delete', $conversation);

            $conversation->deleteWithFiles();

            return $this->ok(null);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['conversation' => $conversation->id]);
        }
    }
}
