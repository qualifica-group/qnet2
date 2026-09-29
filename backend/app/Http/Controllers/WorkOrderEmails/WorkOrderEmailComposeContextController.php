<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\WorkOrderEmailComposeContextBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/work-orders/{workOrder}/emails/compose-context (spec 0175,
 * D-5/D-6/D-7/D-9). Gated by `sendEmail` (not `viewEmails`): the compose
 * context only matters to an actor about to draft/send, mirroring
 * render-template.
 *
 * Invokable, single action.
 */
class WorkOrderEmailComposeContextController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderEmailComposeContextBuilder $builder) {}

    public function __invoke(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->builder->build($workOrder, $actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id]);
        }
    }
}
