<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Http\JsonResponse;

/**
 * Presence heartbeat (spec 0187): intentionally a no-op. The Sanctum guard
 * already refreshes the token's last_used_at on every authenticated request,
 * which is what the online-users read relies on.
 */
class PresenceHeartbeatController extends BaseApiController
{
    /**
     * POST /api/presence/heartbeat.
     */
    public function __invoke(): JsonResponse
    {
        return $this->noContent();
    }
}
