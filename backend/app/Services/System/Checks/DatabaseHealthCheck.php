<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

use App\Enums\HealthStatusEnum;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Live database health check (spec 0187): a `SELECT 1` round-trip, timed,
 * reporting the active connection driver and database name.
 */
final class DatabaseHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'database';
    }

    public function run(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');

            $details = [
                $this->detail('connection', HealthStatusEnum::Ok, DB::connection()->getDriverName()),
                $this->detail('name', HealthStatusEnum::Ok, DB::connection()->getDatabaseName()),
            ];

            return [
                'status' => $this->statusFromDetails($details),
                'latency_ms' => $this->elapsedMs($startedAt),
                'message' => null,
                'details' => $details,
            ];
        } catch (Throwable) {
            $unreachable = __('system_health.database.unreachable');

            return [
                'status' => HealthStatusEnum::Down,
                'latency_ms' => null,
                'message' => $unreachable,
                'details' => [
                    $this->detail('connection', HealthStatusEnum::Down, null, $unreachable),
                    $this->detail('name', HealthStatusEnum::Down),
                ],
            ];
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
