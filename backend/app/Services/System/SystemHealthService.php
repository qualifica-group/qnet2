<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Enums\HealthStatusEnum;
use App\Http\Controllers\System\SystemHealthController;
use App\Services\System\Checks\DatabaseHealthCheck;
use App\Services\System\Checks\EmailHealthCheck;
use App\Services\System\Checks\HealthCheck;
use App\Services\System\Checks\QueueHealthCheck;
use App\Services\System\Checks\SecurityHealthCheck;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aggregates the live health of the critical subsystems (database, email,
 * queue, security hardening) and the online users for the System Health page (spec 0187).
 * A single checker failing never fails the endpoint: it degrades that
 * check's status to "down" instead.
 *
 * @see SystemHealthController
 */
final readonly class SystemHealthService
{
    /** @var HealthCheck[] */
    private array $checks;

    public function __construct(
        DatabaseHealthCheck $database,
        EmailHealthCheck $email,
        QueueHealthCheck $queue,
        SecurityHealthCheck $security,
        private OnlineUsersService $onlineUsers,
    ) {
        $this->checks = [$database, $email, $queue, $security];
    }

    /**
     * @return array{overall:string, checked_at:string, online:array, checks:array}
     */
    public function handle(): array
    {
        // Step 1: run every checker, isolating unexpected failures per-check.
        $results = array_map(fn (HealthCheck $check): array => $this->runSafely($check), $this->checks);

        // Step 2: overall = worst status among the 4 checks.
        $overall = HealthStatusEnum::worst(...array_map(
            static fn (array $result): HealthStatusEnum => $result['status'],
            $results,
        ));

        // Step 3: add the online users and serialize enums to their wire values for the API contract.
        return [
            'overall' => $overall->value,
            'checked_at' => now()->toIso8601String(),
            'online' => $this->onlineUsers->handle(),
            'checks' => array_map(fn (array $result): array => $this->serialize($result), $results),
        ];
    }

    /**
     * Run a single checker, converting any escaped exception into a safe
     * "down" result instead of letting it bubble up to a 5xx response.
     *
     * @return array{key:string, status:HealthStatusEnum, latency_ms:?int, message:?string, details:array}
     */
    private function runSafely(HealthCheck $check): array
    {
        try {
            $result = $check->run();
        } catch (Throwable $exception) {
            Log::warning('[system-health] checker raised an unexpected exception', [
                'check' => $check->key(),
                'error' => $exception->getMessage(),
            ]);

            $result = [
                'status' => HealthStatusEnum::Down,
                'latency_ms' => null,
                'message' => __('system_health.check_failed'),
                'details' => [],
            ];
        }

        return [...$result, 'key' => $check->key()];
    }

    /**
     * @param  array{key:string, status:HealthStatusEnum, latency_ms:?int, message:?string, details:array}  $result
     */
    private function serialize(array $result): array
    {
        return [
            'key' => $result['key'],
            'status' => $result['status']->value,
            'latency_ms' => $result['latency_ms'],
            'message' => $result['message'],
            'details' => array_map(static fn (array $detail): array => [
                'key' => $detail['key'],
                'status' => $detail['status']->value,
                'value' => $detail['value'],
                'message' => $detail['message'],
            ], $result['details']),
        ];
    }
}
