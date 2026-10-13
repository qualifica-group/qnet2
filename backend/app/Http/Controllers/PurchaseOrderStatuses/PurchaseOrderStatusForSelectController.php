<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseOrderStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\PurchaseOrderStatusLabelForSelectResource;
use App\Services\PurchaseOrderStatusLabelService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/purchase-order-statuses/for-select: the whole catalogue (7 rows, by
 * sort_order) the frontend loads once to render every ODA status badge (spec
 * 0248, D-4). No input and no permission gate beyond `auth:sanctum` (ADR 0011):
 * every user who sees an order needs the presentation of its status.
 */
class PurchaseOrderStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly PurchaseOrderStatusLabelService $service) {}

    public function __invoke(): JsonResponse
    {
        try {
            $rows = $this->service->all();

            return $this->paginatedResponse(
                PurchaseOrderStatusLabelForSelectResource::collection($rows),
                $rows->count(),
                0,
                max($rows->count(), 1),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
