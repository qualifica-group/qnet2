<?php

namespace App\Http\Controllers\WorkOrders;

use App\Broadcasting\WorkOrderChatChannel;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderChat\ListWorkOrderChatMessagesRequest;
use App\Http\Requests\WorkOrderChat\MarkWorkOrderChatReadRequest;
use App\Http\Requests\WorkOrderChat\WorkOrderChatRequest;
use App\Http\Requests\WorkOrderChat\WriteWorkOrderChatMessageRequest;
use App\Http\Resources\WorkOrderChatMessageResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Services\WorkOrders\Chat\WorkOrderChatAccess;
use App\Services\WorkOrders\Chat\WorkOrderChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The project work order chat (spec 0352). Every endpoint runs the ordered checks
 * of the data_contract inside its try block (guard()), so each error answers with
 * the envelope: 403 policy, 404 not project, 403 team, 422 payload, 409 closed.
 */
class WorkOrderChatController extends BaseApiController
{
    public function __construct(
        private readonly WorkOrderChatService $chat,
        private readonly WorkOrderChatAccess $access,
    ) {}

    /**
     * GET /api/work-orders/{workOrder}/chat/messages
     */
    public function index(ListWorkOrderChatMessagesRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder);
            $page = $this->chat->page($workOrder, $request->beforeId(), $request->limit());
            $lastRead = $this->chat->lastReadMessageId($workOrder, $user);

            return $this->ok([
                'messages' => WorkOrderChatMessageResource::collection($page['messages'])->resolve(),
                'has_more' => $page['has_more'],
                'can_write' => $this->access->canWrite($user, $workOrder),
                'can_moderate' => $this->access->canModerate($user),
                'unread_count' => $this->chat->unreadCount($workOrder, $user, $lastRead),
                'last_read_message_id' => $lastRead,
                'channel' => WorkOrderChatChannel::name($workOrder),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/chat/messages
     */
    public function store(WriteWorkOrderChatMessageRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $message = $this->chat->post($workOrder, $user, $request->validated('body'));

            return $this->created(new WorkOrderChatMessageResource($message), 'Message sent');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * PATCH /api/work-orders/{workOrder}/chat/messages/{chatMessage}
     */
    public function update(WriteWorkOrderChatMessageRequest $request, WorkOrder $workOrder, WorkOrderChatMessage $chatMessage): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $message = $this->chat->update($workOrder, $user, $chatMessage, $request->validated('body'));

            return $this->ok(new WorkOrderChatMessageResource($message), 'Message updated');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * DELETE /api/work-orders/{workOrder}/chat/messages/{chatMessage}
     */
    public function destroy(Request $request, WorkOrder $workOrder, WorkOrderChatMessage $chatMessage): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $message = $this->chat->delete($workOrder, $user, $chatMessage);

            return $this->ok(new WorkOrderChatMessageResource($message), 'Message deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/chat/read
     */
    public function read(MarkWorkOrderChatReadRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder);

            return $this->ok($this->chat->markRead($workOrder, $user, (int) $request->validated('last_message_id')));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * The ordered checks of every endpoint; returns the acting user.
     */
    private function guard(Request $request, WorkOrder $workOrder, bool $requiresOpen = false): User
    {
        $user = $request->user();

        // Step 1: policy view, project only, team or super-admin
        $this->access->assertCanRead($user, $workOrder);

        // Step 2: payload validation (requests only)
        if ($request instanceof WorkOrderChatRequest) {
            $request->validatePayload();
        }

        // Step 3: no writes on a completed or closed work order
        if ($requiresOpen) {
            $this->access->assertOpen($workOrder);
        }

        return $user;
    }
}
