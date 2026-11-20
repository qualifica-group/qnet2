<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Http\Requests\LinkWorkOrderRequest;
use Integrations\Formalab\Http\Resources\WorkOrderLinkResource;
use Integrations\Formalab\Services\WorkOrderLinkService;
use Throwable;

/**
 * The Formalab tab of a work order. Authorization is the work order's own
 * `view` / `update` policy.
 */
class WorkOrderLinkController extends BaseApiController
{
    private const int BAD_GATEWAY = 502;

    public function __construct(private readonly WorkOrderLinkService $service) {}

    /**
     * GET /api/integrations/formalab/work-orders/{workOrder}.
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        try {
            Gate::authorize('view', $workOrder);

            return $this->respond($workOrder);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/integrations/formalab/work-orders/{workOrder}.
     */
    public function update(LinkWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->service->set($workOrder, (int) $request->validated('edition_id'), $request->user());

            return $this->respond($workOrder);
        } catch (FormalabException) {
            return $this->fail(__('formalab::messages.unreachable'), self::BAD_GATEWAY);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/integrations/formalab/work-orders/{workOrder}/sync: re-read the linked edition now.
     */
    public function sync(WorkOrder $workOrder): JsonResponse
    {
        try {
            Gate::authorize('update', $workOrder);

            $this->service->refresh($workOrder);

            return $this->respond($workOrder);
        } catch (FormalabException) {
            return $this->fail(__('formalab::messages.unreachable'), self::BAD_GATEWAY);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/integrations/formalab/work-orders/{workOrder}.
     */
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        try {
            Gate::authorize('update', $workOrder);

            $this->service->remove($workOrder);

            return $this->respond($workOrder);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function respond(WorkOrder $workOrder): JsonResponse
    {
        return $this->ok(new WorkOrderLinkResource([
            'eligible' => $this->service->isEligible($workOrder),
            'can_manage' => Gate::allows('update', $workOrder),
            'link' => $this->service->find($workOrder),
        ]));
    }
}
