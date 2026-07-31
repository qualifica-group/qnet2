<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Campaigns\CampaignForSelectRequest;
use App\Http\Resources\CampaignForSelectResource;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/campaigns/for-select — minimal, searchable, paginated campaign
 * list feeding entity-backed selects (spec 0024, ADR 0011), mirroring
 * ProjectForSelectController. Feeds the Lead form's campaign field.
 *
 * Thin invokable controller: validation (CampaignForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see CampaignService::forSelect
 */
class CampaignForSelectController extends BaseApiController
{
    public function __construct(private readonly CampaignService $service) {}

    public function __invoke(CampaignForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                CampaignForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
