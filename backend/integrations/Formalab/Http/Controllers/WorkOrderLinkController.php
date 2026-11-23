<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Integrations\Formalab\Exceptions\FormalabException;
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
     * POST /api/integrations/formalab/work-orders/{workOrder}/sync: re-read
     * from Formalab the edition chosen in the class select and write the fields.
     */
    public function sync(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            Gate::authorize('update', $workOrder);

            $this->service->sync($workOrder, $request->user());

            return $this->respond($workOrder);
        } catch (FormalabException) {
            return $this->fail(__('formalab::messages.unreachable'), self::BAD_GATEWAY);
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
