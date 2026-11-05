<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrders\RequestWorkOrderUpdateRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderUpdateRequester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/work-orders/{workOrder}/request-update (spec 0352). Invokable,
 * single action; the work order itself is never written, so `data` is null.
 */
class WorkOrderRequestUpdateController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderUpdateRequester $requester) {}

    public function __invoke(RequestWorkOrderUpdateRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('requestUpdate', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $this->requester->handle($workOrder, $request->toData(), $actor);

            return $this->ok(null, 'Update request sent.');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id]);
        }
    }
}
