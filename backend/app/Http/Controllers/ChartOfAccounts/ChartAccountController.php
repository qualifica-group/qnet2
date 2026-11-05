<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ChartOfAccounts\StoreChartAccountRequest;
use App\Http\Requests\ChartOfAccounts\UpdateChartAccountRequest;
use App\Http\Resources\ChartAccountResource;
use App\Models\ChartAccount;
use App\Services\ChartOfAccounts\ChartAccountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `chart-accounts` resource (spec 0348): groups and
 * accounts. Thin controller: validation (FormRequest), authorization
 * (ChartAccountPolicy), Service call, response.
 *
 * @see ChartAccountService
 */
class ChartAccountController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ChartAccountService $service) {}

    public function show(ChartAccount $chartAccount): JsonResponse
    {
        try {
            $this->authorize('view', $chartAccount);

            return $this->ok(new ChartAccountResource($this->service->present($chartAccount)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['chartAccount' => $chartAccount->id]);
        }
    }

    public function store(StoreChartAccountRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ChartAccount::class);

            $chartAccount = $this->service->create($request->validated());

            return $this->ok(new ChartAccountResource($chartAccount), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateChartAccountRequest $request, ChartAccount $chartAccount): JsonResponse
    {
        try {
            $this->authorize('update', $chartAccount);

            $chartAccount = $this->service->update($chartAccount, $request->validated());

            return $this->ok(new ChartAccountResource($chartAccount));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['chartAccount' => $chartAccount->id]);
        }
    }

    public function destroy(ChartAccount $chartAccount): JsonResponse
    {
        try {
            $this->authorize('delete', $chartAccount);

            $this->service->delete($chartAccount);

            return $this->ok(null);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['chartAccount' => $chartAccount->id]);
        }
    }
}
