<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

use App\Enums\HealthStatusEnum;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queue health check (spec 0187): default connection, pending jobs (`jobs`)
 * and failed jobs (`failed_jobs`). `sync` runs jobs inline (no worker), failed
 * jobs or a backlog above the configured threshold degrade the check.
 */
final class QueueHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'queue';
    }

    public function run(): array
    {
        $startedAt = microtime(true);

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $unreachable = __('system_health.queue.unreachable');

            return [
                'status' => HealthStatusEnum::Down,
                'latency_ms' => null,
                'message' => $unreachable,
                'details' => [
                    $this->connectionDetail(),
                    $this->detail('pending', HealthStatusEnum::Down, null, $unreachable),
                    $this->detail('failed', HealthStatusEnum::Down),
                ],
            ];
        }

        $details = [
            $this->connectionDetail(),
            $this->detail(
                'pending',
                $pending > (int) config('system-health.queue_pending_threshold') ? HealthStatusEnum::Degraded : HealthStatusEnum::Ok,
                (string) $pending,
            ),
            $this->detail('failed', $failed > 0 ? HealthStatusEnum::Degraded : HealthStatusEnum::Ok, (string) $failed),
        ];

        return [
            'status' => $this->statusFromDetails($details),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => null,
            'details' => $details,
        ];
    }

    private function connectionDetail(): array
    {
        $connection = (string) config('queue.default');

        return $this->detail('connection', $connection === 'sync' ? HealthStatusEnum::Degraded : HealthStatusEnum::Ok, $connection);
    }
}
