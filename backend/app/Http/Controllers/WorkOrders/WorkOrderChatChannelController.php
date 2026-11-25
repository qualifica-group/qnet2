<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderChat\ListWorkOrderChatChannelsRequest;
use App\Http\Resources\WorkOrderChatChannelResource;
use App\Services\WorkOrders\Chat\WorkOrderChatChannels;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * The chat dock's channel list (spec 0432).
 */
class WorkOrderChatChannelController extends BaseApiController
{
    public function __construct(private readonly WorkOrderChatChannels $channels) {}

    /**
     * GET /api/work-order-chat/channels
     */
    public function index(ListWorkOrderChatChannelsRequest $request): JsonResponse
    {
        try {
            $channels = $this->channels->forUser($request->user());

            return $this->ok(WorkOrderChatChannelResource::collection($channels)->resolve($request));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
