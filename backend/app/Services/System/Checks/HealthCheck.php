<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

use App\Enums\HealthStatusEnum;

/**
 * Contract for a single system-health checker (spec 0187). Each
 * implementation runs a live, timeout-bounded probe of one subsystem and
 * must never let an exception escape to the caller — a failed probe is
 * reported as a "down" status, not a thrown error.
 */
interface HealthCheck
{
    /**
     * Stable identifier of the check (matches the API contract's `checks[].key`).
     */
    public function key(): string;

    /**
     * Run the live probe.
     *
     * @return array{
     *     status: HealthStatusEnum,
     *     latency_ms: ?int,
     *     message: ?string,
     *     details: array<int, array{key:string, status:HealthStatusEnum, value:?string, message:?string}>
     * }
     */
    public function run(): array;
}
