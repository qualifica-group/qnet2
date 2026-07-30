<?php

namespace App\Http\Controllers\PaymentMethods;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PaymentMethods\StorePaymentMethodRequest;
use App\Http\Requests\PaymentMethods\UpdatePaymentMethodRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\PaymentMethodService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `payment-methods` resource (spec 0068), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (PaymentMethodPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see PaymentMethodService
 */
class PaymentMethodController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PaymentMethodService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/payment-methods/{paymentMethod} — single payment method (view
     * row-action).
     */
    public function show(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $this->authorize('view', $paymentMethod);

            $paymentMethod = $this->service->loadDetail($paymentMethod);

            return $this->okWithPermissions(
                new PaymentMethodResource($paymentMethod),
                $this->buildPermissions($request->user(), $paymentMethod),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['paymentMethod' => $paymentMethod->id]);
        }
    }

    /**
     * POST /api/payment-methods — create a new payment method.
     */
    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PaymentMethod::class);

            $paymentMethod = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new PaymentMethodResource($paymentMethod),
                $this->buildPermissions($request->user(), $paymentMethod),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/payment-methods/{paymentMethod} — update an existing
     * payment method.
     */
    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $this->authorize('update', $paymentMethod);

            $paymentMethod = $this->service->update($paymentMethod, $request->toData());

            return $this->okWithPermissions(
                new PaymentMethodResource($paymentMethod),
                $this->buildPermissions($request->user(), $paymentMethod),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['paymentMethod' => $paymentMethod->id]);
        }
    }

    /**
     * DELETE /api/payment-methods/{paymentMethod} — delete a payment method
     * (no guard in this iteration, spec 0068 D-2).
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $this->authorize('delete', $paymentMethod);

            $this->service->delete($paymentMethod);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['paymentMethod' => $paymentMethod->id]);
        }
    }

    /**
     * POST /api/payment-methods/reorder — resequence the rows (D-1). Gated
     * on `payment-methods.update` directly (no single Model instance exists
     * for a bulk reorder, so there is no Policy `update($user, $model)` to
     * delegate to — mirrors RewardStatusController::reorder()).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('payment-methods.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (PaymentMethod $paymentMethod): array => [
                'id' => $paymentMethod->id,
                'sort_order' => $paymentMethod->sort_order,
                // spec 0068, D-5: the table has no `system_key` column, but
                // the shared `status-reorder` frontend feature's contract
                // requires the key to always be present.
                'system_key' => null,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?PaymentMethod $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('payment-methods'), $actor, $model);
    }
}
