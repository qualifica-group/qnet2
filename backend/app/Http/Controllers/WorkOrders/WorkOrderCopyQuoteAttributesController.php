<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrders;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\WorkOrderResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderQuoteAttributeCopier;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * POST /api/work-orders/{workOrder}/copy-quote-attributes (spec 0404 D-D2):
 * "Copia dati dall'offerta" fills the work order's EMPTY flexible fields from
 * its offer(s), never overwriting. No input: the action takes nothing but the
 * work order. Same authorization as editing the work order.
 */
class WorkOrderCopyQuoteAttributesController extends BaseApiController
{
    use AuthorizesRequests;

    private const string RESOURCE = 'work-orders';

    public function __construct(
        private readonly WorkOrderQuoteAttributeCopier $copier,
        private readonly WorkOrderService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $count = DB::transaction(fn (): int => $this->copier->fill($workOrder, $this->copier->quoteIdsOf($workOrder)));
            $workOrder = $this->service->loadDetail($workOrder->fresh());

            return $this->okWithPermissions(
                new WorkOrderResource($workOrder),
                $this->permissionsBuilder->build($this->authorization->resolve(self::RESOURCE), $actor, $workOrder),
                trans_choice(':count field copied from the offer|:count fields copied from the offer', $count, ['count' => $count]),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }
}
