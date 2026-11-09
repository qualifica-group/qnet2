<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/availability (spec 0376): the maintenance screen polls it. The answer
 * comes from EnsureNotInMaintenance: reaching this controller means "available".
 */
class AvailabilityController extends BaseApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->ok();
    }
}
