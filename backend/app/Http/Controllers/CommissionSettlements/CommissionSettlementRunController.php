<?php

namespace App\Http\Controllers\CommissionSettlements;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CommissionSettlements\CommissionSettlementRunRequest;
use App\Http\Resources\CommissionSettlementRunResource;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\User;
use App\Services\Commissions\Settlements\SettlementRunner;
use App\Services\Commissions\Settlements\SettlementRunReader;
use App\Services\Commissions\Settlements\SettlementRunView;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the settlement runs (spec 0213, D-6). A run is not a permission
 * resource: launching it needs `commission-settlements.create`, reading it
 * `commission-settlements.viewAny`.
 *
 * @see SettlementRunner
 */
class CommissionSettlementRunController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SettlementRunner $runner,
        private readonly SettlementRunReader $reader,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * POST /api/commission-settlement-runs
     */
    public function store(CommissionSettlementRunRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CommissionSettlement::class);

            return $this->respond($request->user(), $this->runner->handle($request->user(), $request->toData()), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/commission-settlement-runs/{commissionSettlementRun}
     */
    public function show(Request $request, CommissionSettlementRun $commissionSettlementRun): JsonResponse
    {
        try {
            $this->authorize('viewAny', CommissionSettlement::class);

            return $this->respond($request->user(), $this->reader->view($commissionSettlementRun));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionSettlementRun' => $commissionSettlementRun->id]);
        }
    }

    private function respond(User $actor, SettlementRunView $view, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        $permissions = $this->permissionsBuilder->build($this->authorization->resolve('commission-settlements'), $actor, null);

        return $this->okWithPermissions(new CommissionSettlementRunResource($view, $permissions['fields']), $permissions, $message, $status);
    }
}
