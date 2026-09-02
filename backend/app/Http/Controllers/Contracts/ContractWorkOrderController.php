<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\HttpStatusEnum;
use App\Enums\WorkOrderType;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\GenerateContractWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\Contract;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Contracts\ContractActionAvailability;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/contracts/{contract}/work-orders (spec 0095, D-3/D-6/D-11):
 * generates ONE work order from a chosen group of the contract's own offer
 * lines. Same doubled gate as ContractProgrammableLinesController
 * (`contracts.program` ANDed with ContractActionAvailability::mayProgram()).
 * Delegates to WorkOrderService::create() (AC-035, constraint: "riuso
 * obbligatorio"), never reimplementing the numbering/transaction/line-
 * membership logic; the response is the SAME WorkOrderResource shape as
 * POST /api/work-orders.
 */
class ContractWorkOrderController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkOrderService $service,
        private readonly ContractActionAvailability $availability,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(GenerateContractWorkOrderRequest $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorize('program', $contract);
            abort_unless($this->availability->mayProgram($contract), 403);

            $data = CreateWorkOrderData::forContractGeneration(
                quoteId: $contract->quote_id,
                title: (string) $request->validated('title'),
                type: WorkOrderType::from((string) $request->validated('type')),
                quoteLineIds: (array) $request->validated('quote_line_ids'),
            );

            $workOrder = $this->service->create($data);

            return $this->okWithPermissions(
                new WorkOrderResource($workOrder),
                $this->buildPermissions($request->user(), $workOrder),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }

    /**
     * The `permissions` block for the generated work order (spec 0004),
     * scoped to the `work-orders` resource — the CREATED entity, not the
     * contract this endpoint hangs off.
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?WorkOrder $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('work-orders'), $actor, $model);
    }
}
