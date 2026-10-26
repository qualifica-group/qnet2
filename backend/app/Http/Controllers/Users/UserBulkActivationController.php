<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Users\UserBulkActivationRequest;
use App\Services\Users\UserBulkActivation;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/users/bulk-activation (spec 0310): per-user outcome, a refused
 * user never blocks the others.
 */
class UserBulkActivationController extends BaseApiController
{
    public function __construct(private readonly UserBulkActivation $bulk) {}

    public function __invoke(UserBulkActivationRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->bulk->apply(
                $request->user(),
                $request->boolean('is_active'),
                $request->ids(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
