<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseRequestLineStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\PurchaseRequestLineStatusForSelectResource;
use App\Services\PurchaseRequestLineStatusLabelService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/purchase-request-line-statuses/for-select (spec 0248, D-4): the
 * whole catalog (a handful of rows) in display order, no paging parameters.
 * No gate beyond `auth:sanctum` (ADR 0011): every RDA screen renders badges
 * from it.
 */
class PurchaseRequestLineStatusLabelForSelectController extends BaseApiController
{
    public function __construct(private readonly PurchaseRequestLineStatusLabelService $service) {}

    public function __invoke(): JsonResponse
    {
        try {
            $items = $this->service->catalog();

            return $this->paginatedResponse(
                PurchaseRequestLineStatusForSelectResource::collection($items),
                $items->count(),
                0,
                max($items->count(), 1),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
