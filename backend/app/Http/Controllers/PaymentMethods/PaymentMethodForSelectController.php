<?php

namespace App\Http\Controllers\PaymentMethods;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PaymentMethods\PaymentMethodForSelectRequest;
use App\Http\Resources\PaymentMethodForSelectResource;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/payment-methods/for-select — minimal, searchable, paginated
 * payment method list feeding entity-backed selects (spec 0068, ADR 0011 the
 * for-select standard).
 *
 * Thin invokable controller: validation (PaymentMethodForSelectRequest),
 * server-side authorization (payment-methods.viewAny via PaymentMethodPolicy),
 * Service call, paginated response.
 *
 * @see PaymentMethodService::forSelect
 */
class PaymentMethodForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly PaymentMethodService $service) {}

    public function __invoke(PaymentMethodForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', PaymentMethod::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                PaymentMethodForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
