<?php

namespace App\Http\Controllers\PurchaseRequests;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequests\PurchaseRequestCloseRequest;
use App\Http\Requests\PurchaseRequests\PurchaseRequestWriteRequest;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestClosureService;
use App\Services\PurchaseRequests\PurchaseRequestNotifier;
use App\Services\PurchaseRequests\PurchaseRequestService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the `purchase-requests` resource (spec 0208). Thin controller:
 * FormRequest validation, server-side authorization (PurchaseRequestPolicy),
 * Service call. The lists are the generic `purchase-requests` and
 * `purchase-request-lines` table domains; line status changes live in
 * PurchaseRequestLineStatusController.
 *
 * @see PurchaseRequestService
 */
class PurchaseRequestController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PurchaseRequestService $service,
        private readonly PurchaseRequestClosureService $closure,
        private readonly PurchaseRequestNotifier $notifier,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/purchase-requests/{purchaseRequest}
     */
    public function show(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseRequest);

            return $this->detailResponse($request->user(), $this->service->detail($purchaseRequest));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequest' => $purchaseRequest->id]);
        }
    }

    /**
     * POST /api/purchase-requests
     */
    public function store(PurchaseRequestWriteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PurchaseRequest::class);

            $created = $this->service->create($request->toData(), $request->user());

            return $this->detailResponse($request->user(), $created, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/purchase-requests/{purchaseRequest} (409 once closed)
     */
    public function update(PurchaseRequestWriteRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('update', $purchaseRequest);

            $updated = $this->service->update($purchaseRequest, $request->toData(), $request->user());

            return $this->detailResponse($request->user(), $updated);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequest' => $purchaseRequest->id]);
        }
    }

    /**
     * DELETE /api/purchase-requests/{purchaseRequest} (409 with ordered or received lines)
     */
    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('delete', $purchaseRequest);

            $this->service->delete($purchaseRequest);

            return $this->ok(null, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequest' => $purchaseRequest->id]);
        }
    }

    /**
     * GET /api/purchase-requests/{purchaseRequest}/closure
     */
    public function closure(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('view', $purchaseRequest);

            $info = $this->closure->info($purchaseRequest);
            $info['can_close'] = $info['can_close'] && $request->user()->can('close', $purchaseRequest);

            return $this->ok($info);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequest' => $purchaseRequest->id]);
        }
    }

    /**
     * POST /api/purchase-requests/{purchaseRequest}/close (409 when closed, 422 `reason` when forced)
     */
    public function close(PurchaseRequestCloseRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('close', $purchaseRequest);

            $this->closure->close($purchaseRequest, $request->user(), $request->reason());

            return $this->detailResponse($request->user(), $this->service->detail($purchaseRequest));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequest' => $purchaseRequest->id]);
        }
    }

    /**
     * POST /api/purchase-requests/{purchaseRequest}/notify-manager
     */
    public function notifyManager(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        try {
            $this->authorize('notifyManager', $purchaseRequest);

            $manager = $this->notifier->resend($purchaseRequest, $request->user());

            return $this->ok(null, __('Purchase request sent to :name', ['name' => $manager->name]));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['purchaseRequest' => $purchaseRequest->id]);
        }
    }

    private function detailResponse(User $actor, PurchaseRequest $purchaseRequest, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        $permissions = $this->permissionsBuilder->build($this->authorization->resolve('purchase-requests'), $actor, $purchaseRequest);

        return $this->okWithPermissions(new PurchaseRequestResource($purchaseRequest, $permissions), $permissions, $message, $status);
    }
}
