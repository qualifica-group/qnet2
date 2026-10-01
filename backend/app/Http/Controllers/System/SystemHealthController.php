<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Services\System\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * System Health endpoint (spec 0187). Authorization is the `super-admin`
 * route middleware (EnsureSuperAdmin: 401 anonymous, 403 otherwise).
 *
 * @see SystemHealthService
 */
class SystemHealthController extends BaseApiController
{
    public function __construct(private readonly SystemHealthService $service) {}

    /**
     * GET /api/system-health.
     */
    public function __invoke(): JsonResponse
    {
        try {
            return $this->ok($this->service->handle(), __('system_health.ok'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
