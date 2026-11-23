<?php

namespace App\Http\Controllers\WorkOrders;

use App\Exceptions\WorkOrderPageConflictException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderChat\WorkOrderChatRequest;
use App\Http\Requests\WorkOrderPage\SaveWorkOrderPageRequest;
use App\Http\Requests\WorkOrderPage\StoreWorkOrderPagePollRequest;
use App\Http\Requests\WorkOrderPage\VoteWorkOrderPagePollRequest;
use App\Http\Resources\WorkOrderPagePollResource;
use App\Http\Resources\WorkOrderPageResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPagePoll;
use App\Services\WorkOrders\Chat\WorkOrderChatAccess;
use App\Services\WorkOrders\Page\WorkOrderPagePollService;
use App\Services\WorkOrders\Page\WorkOrderPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The notes page of a project work order (spec 0420). Same ordered checks as the
 * chat (guard()): 403 policy, 404 not project, 403 team, 422 payload, 409 closed.
 */
class WorkOrderPageController extends BaseApiController
{
    public function __construct(
        private readonly WorkOrderPageService $pages,
        private readonly WorkOrderPagePollService $polls,
        private readonly WorkOrderChatAccess $access,
    ) {}

    /**
     * GET /api/work-orders/{workOrder}/page
     */
    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder);

            return $this->ok(new WorkOrderPageResource($this->pages->view($workOrder, $user)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * PUT /api/work-orders/{workOrder}/page
     */
    public function update(SaveWorkOrderPageRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $view = $this->pages->save($workOrder, $user, $request->validated('content'), (int) $request->validated('version'));

            return $this->ok(new WorkOrderPageResource($view), 'Page saved');
        } catch (WorkOrderPageConflictException $exception) {
            return $this->conflict($request, $workOrder, $exception);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/page/polls
     */
    public function storePoll(StoreWorkOrderPagePollRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $poll = $this->polls->create($workOrder, $user, $request->validated('question'), $request->validated('options'));

            return $this->created(WorkOrderPagePollResource::make($poll)->resolve($request), 'Poll created');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * PUT /api/work-orders/{workOrder}/page/polls/{pagePoll}/vote
     */
    public function vote(VoteWorkOrderPagePollRequest $request, WorkOrder $workOrder, WorkOrderPagePoll $pagePoll): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $poll = $this->polls->vote($pagePoll, $user, $request->validated('option_key'));

            return $this->ok(WorkOrderPagePollResource::make($poll)->resolve($request), 'Vote saved');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * DELETE /api/work-orders/{workOrder}/page/polls/{pagePoll}/vote
     */
    public function unvote(Request $request, WorkOrder $workOrder, WorkOrderPagePoll $pagePoll): JsonResponse
    {
        try {
            $user = $this->guard($request, $workOrder, requiresOpen: true);
            $poll = $this->polls->withdraw($pagePoll, $user);

            return $this->ok(WorkOrderPagePollResource::make($poll)->resolve($request), 'Vote withdrawn');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /** 409 with the current page, so the client can offer the reload (D-3). */
    private function conflict(Request $request, WorkOrder $workOrder, WorkOrderPageConflictException $exception): JsonResponse
    {
        $current = new WorkOrderPageResource($this->pages->view($workOrder, $request->user()));

        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'code' => WorkOrderPageConflictException::CODE,
            'data' => $current->resolve($request),
        ], 409);
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
