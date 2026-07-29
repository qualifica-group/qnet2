<?php

namespace App\Http\Controllers\CommissionConfigurations;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CommissionConfigurations\StoreCommissionConfigurationRequest;
use App\Http\Requests\CommissionConfigurations\UpdateCommissionConfigurationRequest;
use App\Http\Resources\CommissionConfigurationResource;
use App\Models\CommissionConfiguration;
use App\Models\User;
use App\Services\CommissionConfigurationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CommissionConfigurationController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CommissionConfigurationService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, CommissionConfiguration $commissionConfiguration): JsonResponse
    {
        try {
            $this->authorize('view', $commissionConfiguration);
            $model = $this->service->loadDetail($commissionConfiguration);
            $permissions = $this->permissions($request->user(), $model);

            return $this->okWithPermissions(
                new CommissionConfigurationResource($model, $permissions['fields']),
                $permissions,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionConfiguration' => $commissionConfiguration->id]);
        }
    }

    public function store(StoreCommissionConfigurationRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CommissionConfiguration::class);
            $model = $this->service->create($request->toData());
            $permissions = $this->permissions($request->user(), $model);

            return $this->okWithPermissions(
                new CommissionConfigurationResource($model, $permissions['fields']),
                $permissions,
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(
        UpdateCommissionConfigurationRequest $request,
        CommissionConfiguration $commissionConfiguration,
    ): JsonResponse {
        try {
            $this->authorize('update', $commissionConfiguration);
            $model = $this->service->update($commissionConfiguration, $request->toData());
            $permissions = $this->permissions($request->user(), $model);

            return $this->okWithPermissions(
                new CommissionConfigurationResource($model, $permissions['fields']),
                $permissions,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionConfiguration' => $commissionConfiguration->id]);
        }
    }

    public function destroy(CommissionConfiguration $commissionConfiguration): JsonResponse
    {
        try {
            $this->authorize('delete', $commissionConfiguration);
            $this->service->delete($commissionConfiguration);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionConfiguration' => $commissionConfiguration->id]);
        }
    }

    /**
     * @return array{
     *     resource: array<string, bool>,
     *     fields: array<string, array<string, bool>>,
     *     actions: array<string, bool>
     * }
     */
    private function permissions(User $actor, CommissionConfiguration $model): array
    {
        return $this->permissionsBuilder->build(
            $this->authorization->resolve('commission-configurations'),
            $actor,
            $model,
        );
    }
}
