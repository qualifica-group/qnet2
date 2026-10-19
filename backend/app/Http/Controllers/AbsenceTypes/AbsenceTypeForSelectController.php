<?php

namespace App\Http\Controllers\AbsenceTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Services\AbsenceTypeService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/absence-types/for-select (spec 0278, ADR 0011). No permission gate
 * beyond `auth:sanctum`: every employee picks a type when filing a request.
 */
class AbsenceTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly AbsenceTypeService $service) {}

    public function __invoke(): JsonResponse
    {
        try {
            return $this->ok($this->service->forSelect());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
