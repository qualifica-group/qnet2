<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Controllers;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;
use Integrations\Formalab\Http\Requests\ClassForSelectRequest;
use Integrations\Formalab\Http\Resources\ClassForSelectResource;
use Integrations\Formalab\Services\ClassSelectOptions;
use Throwable;

/**
 * GET /api/integrations/formalab/classes/for-select: the options of the work
 * order class select (spec 0426 D-4). Gate: auth:sanctum only (ADR 0011).
 */
class ClassForSelectController extends BaseApiController
{
    public function __construct(private readonly ClassSelectOptions $options) {}

    public function __invoke(ClassForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->options->handle($request->toData());

            return $this->paginatedResponse(
                ClassForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
