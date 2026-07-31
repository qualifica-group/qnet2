<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Users\UserForSelectRequest;
use App\Http\Resources\UserForSelectResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/users/for-select — minimal, searchable, paginated user list feeding
 * the Role-form user multi-select (ADR 0011, the for-select standard).
 *
 * Thin invokable controller: validation (UserForSelectRequest), Service
 * call, paginated response. The query/search/hydration logic lives in
 * UserService::forSelect, not here. No permission gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31): option lists feed
 * forms whose actor may legitimately lack browse rights on the source
 * module.
 *
 * @see UserService::forSelect
 */
class UserForSelectController extends BaseApiController
{
    public function __construct(private readonly UserService $service) {}

    public function __invoke(UserForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                UserForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
