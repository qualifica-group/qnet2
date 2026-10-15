<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\MultiQuoteWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Services\Contracts\MultiQuoteContractGate;
use App\Services\Contracts\MultiQuoteWorkOrderProgrammer;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/contracts/multi-quote-work-orders (spec 0267, D-3/D-4): ONE
 * multi-offer work order from lines of several contracts. Same doubled gate as
 * the GET, applied to EVERY contract; the response matches
 * POST /api/contracts/{contract}/work-orders.
 */
class ContractMultiQuoteWorkOrderController extends BaseApiController
{
    public function __construct(
        private readonly MultiQuoteContractGate $gate,
        private readonly MultiQuoteWorkOrderProgrammer $programmer,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(MultiQuoteWorkOrderRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();
            $contracts = $this->gate->load($actor, (array) $request->validated('contract_ids'));

            $workOrder = $this->programmer->handle($contracts, $request->validated());

            return $this->okWithPermissions(
                new WorkOrderResource($workOrder),
                $this->permissionsBuilder->build($this->authorization->resolve('work-orders'), $actor, $workOrder),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
