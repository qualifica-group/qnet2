<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Contracts\GenerateContractWorkOrdersBatchRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\Contract;
use App\Services\Contracts\ContractActionAvailability;
use App\Services\Contracts\ContractProgramBatch;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/contracts/{contract}/work-orders/batch (spec 0215, D-1): creates
 * several work orders from groups of the contract's offer lines, all or
 * nothing. Same doubled gate as ContractWorkOrderController
 * (`contracts.program` ANDed with ContractActionAvailability::mayProgram()).
 */
class ContractWorkOrderBatchController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ContractProgramBatch $batch,
        private readonly ContractActionAvailability $availability,
    ) {}

    public function __invoke(GenerateContractWorkOrdersBatchRequest $request, Contract $contract): JsonResponse
    {
        try {
            $this->authorize('program', $contract);
            abort_unless($this->availability->mayProgram($contract), 403);

            $workOrders = $this->batch->handle($contract, (array) $request->validated('groups'));

            return $this->created(WorkOrderResource::collection($workOrders));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['contract' => $contract->id]);
        }
    }
}
