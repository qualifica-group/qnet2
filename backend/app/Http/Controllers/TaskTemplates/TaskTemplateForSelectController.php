<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskTemplates;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskTemplates\TaskTemplateForSelectRequest;
use App\Http\Resources\TaskTemplateForSelectResource;
use App\Services\TaskTemplateService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/task-templates/for-select — minimal, searchable, paginated
 * active-template list feeding the "Modello di Task" picker on the Commessa
 * create form and the contract "Programma" dialog (spec 0124, D-9; ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation (TaskTemplateForSelectRequest),
 * Service call, paginated response. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): generating a Commessa
 * requires no `task-templates.*` permission (D-8).
 *
 * @see TaskTemplateService::forSelect
 */
class TaskTemplateForSelectController extends BaseApiController
{
    public function __construct(private readonly TaskTemplateService $service) {}

    public function __invoke(TaskTemplateForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TaskTemplateForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
