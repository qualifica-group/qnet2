<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuoteSystemStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteSystemStatuses\QuoteSystemStatusForSelectRequest;
use App\Http\Resources\QuoteSystemStatusForSelectResource;
use App\Services\QuoteSystemStatuses\QuoteSystemStatusService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/quote-system-statuses/for-select (spec 0404 part A). No permission
 * beyond auth:sanctum (ADR 0011): it feeds the workflow configurator and the
 * Offerte forms.
 */
class QuoteSystemStatusForSelectController extends BaseApiController
{
    public function __construct(private readonly QuoteSystemStatusService $service) {}

    public function __invoke(QuoteSystemStatusForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                QuoteSystemStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
