<?php

namespace App\Http\Controllers\CollectiveAgreements;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CollectiveAgreements\StoreCollectiveAgreementRequest;
use App\Http\Requests\CollectiveAgreements\UpdateCollectiveAgreementRequest;
use App\Http\Resources\CollectiveAgreementResource;
use App\Models\CollectiveAgreement;
use App\Services\CollectiveAgreementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `collective-agreements` resource (spec 0272). Thin controller:
 * validation (FormRequest), authorization (CollectiveAgreementPolicy), Service call, response.
 *
 * @see CollectiveAgreementService
 */
class CollectiveAgreementController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CollectiveAgreementService $service) {}

    public function show(CollectiveAgreement $collectiveAgreement): JsonResponse
    {
        try {
            $this->authorize('view', $collectiveAgreement);

            return $this->ok(new CollectiveAgreementResource($this->service->withRelations($collectiveAgreement)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['collectiveAgreement' => $collectiveAgreement->id]);
        }
    }

    public function store(StoreCollectiveAgreementRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CollectiveAgreement::class);

            $collectiveAgreement = $this->service->create($request->toData());

            return $this->ok(new CollectiveAgreementResource($collectiveAgreement), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateCollectiveAgreementRequest $request, CollectiveAgreement $collectiveAgreement): JsonResponse
    {
        try {
            $this->authorize('update', $collectiveAgreement);

            $collectiveAgreement = $this->service->update($collectiveAgreement, $request->toData());

            return $this->ok(new CollectiveAgreementResource($collectiveAgreement));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['collectiveAgreement' => $collectiveAgreement->id]);
        }
    }

    public function destroy(CollectiveAgreement $collectiveAgreement): JsonResponse
    {
        try {
            $this->authorize('delete', $collectiveAgreement);

            $this->service->delete($collectiveAgreement);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['collectiveAgreement' => $collectiveAgreement->id]);
        }
    }
}
