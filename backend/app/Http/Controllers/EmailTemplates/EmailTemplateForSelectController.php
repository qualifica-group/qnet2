<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmailTemplates;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EmailTemplates\EmailTemplateForSelectRequest;
use App\Http\Resources\EmailTemplateForSelectResource;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/email-templates/for-select — minimal, searchable, paginated
 * active-email-template list for ONE `module`, feeding the Commessa email
 * composer's template picker (spec 0175, ADR 0011).
 *
 * Thin invokable controller: validation (EmailTemplateForSelectRequest),
 * Service call, paginated response. No permission gate beyond `auth:sanctum`
 * (ADR 0011, amended 2026-07-31).
 *
 * @see EmailTemplateService::forSelect
 */
class EmailTemplateForSelectController extends BaseApiController
{
    public function __construct(private readonly EmailTemplateService $service) {}

    public function __invoke(EmailTemplateForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                EmailTemplateForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
