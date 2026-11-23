<?php

declare(strict_types=1);

namespace Integrations\Formalab\Health;

use App\Enums\HealthStatusEnum;
use App\Services\System\Checks\AbstractHealthCheck;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\CourseFieldWriter;
use Integrations\Formalab\Services\FormalabClient;
use Integrations\Formalab\Support\Trigger;

/**
 * Formalab health (spec 0422, D-10): configuration, live connection probe,
 * freshness of the last catalogue sync and failed calls in the last 24 hours.
 */
final class FormalabHealthCheck extends AbstractHealthCheck
{
    /** A sync older than this many intervals is stale. */
    private const int STALE_AFTER_INTERVALS = 3;

    public function __construct(
        private readonly FormalabClient $client,
        private readonly CourseFieldWriter $courseFields,
    ) {}

    public function key(): string
    {
        return 'formalab';
    }

    public function run(): array
    {
        // Step 1: configuration decides whether the live probe makes sense
        $configuration = $this->configurationDetail();
        $configured = $configuration['status'] === HealthStatusEnum::Ok;

        // Step 2: probe the connection, then the sync freshness, the recent failures and the course fields
        [$connection, $latencyMs] = $configured ? $this->connectionDetail() : [$this->detail('connection', HealthStatusEnum::Down, null, __('formalab::messages.health.not_probed')), null];
        $details = [$configuration, $connection, $this->lastSyncDetail(), $this->recentFailuresDetail(), $this->courseFieldsDetail()];

        // Step 3: the check is as bad as its worst detail
        return [
            'status' => $this->statusFromDetails($details),
            'latency_ms' => $latencyMs,
            'message' => null,
            'details' => $details,
        ];
    }

    private function configurationDetail(): array
    {
        if (! $this->client->isConfigured()) {
            return $this->detail('configuration', HealthStatusEnum::Down, null, __('formalab::messages.not_configured'));
        }

        return $this->detail('configuration', HealthStatusEnum::Ok, (string) parse_url((string) config('formalab.url'), PHP_URL_HOST));
    }

    /**
     * @return array{0: array, 1: int|null}
     */
    private function connectionDetail(): array
    {
        $startedAt = microtime(true);

        try {
            $this->client->get('/api/classi', ['per_page' => 1], Trigger::Health, (int) config('formalab.health_timeout_seconds'));
        } catch (FormalabException $exception) {
            return [$this->detail('connection', HealthStatusEnum::Down, null, $exception->getMessage()), null];
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        return [$this->detail('connection', HealthStatusEnum::Ok, $latency.' ms'), $latency];
    }

    private function lastSyncDetail(): array
    {
        $run = FormalabSyncRun::query()
            ->where('outcome', '!=', FormalabSyncRun::OUTCOME_RUNNING)
            ->latest('started_at')
            ->latest('id')
            ->first();

        if ($run === null) {
            return $this->detail('last_sync', HealthStatusEnum::Down, null, __('formalab::messages.health.never_synced'));
        }

        $value = ($run->finished_at ?? $run->started_at)->toIso8601String();

        if ($run->outcome === FormalabSyncRun::OUTCOME_FAILURE) {
            return $this->detail('last_sync', HealthStatusEnum::Degraded, $value, __('formalab::messages.health.last_sync_failed'));
        }

        $staleAfter = now()->subMinutes(self::STALE_AFTER_INTERVALS * (int) config('formalab.sync_interval_minutes'));

        return ($run->finished_at ?? $run->started_at)->lt($staleAfter)
            ? $this->detail('last_sync', HealthStatusEnum::Degraded, $value, __('formalab::messages.health.sync_stale'))
            : $this->detail('last_sync', HealthStatusEnum::Ok, $value);
    }

    private function recentFailuresDetail(): array
    {
        $failures = FormalabCallLog::query()
            ->where('outcome', FormalabCallLog::OUTCOME_FAILURE)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $failures === 0
            ? $this->detail('recent_failures', HealthStatusEnum::Ok, '0')
            : $this->detail('recent_failures', HealthStatusEnum::Degraded, (string) $failures, __('formalab::messages.health.recent_failures'));
    }

    /**
     * The attributes the category field maps write must still exist under
     * their code: a renamed or deleted one would stop updating without an error.
     */
    private function courseFieldsDetail(): array
    {
        $missing = $this->courseFields->missingCodes();
        $total = $this->courseFields->mappedCodesCount();

        return $missing === []
            ? $this->detail('course_fields', HealthStatusEnum::Ok, $total.'/'.$total)
            : $this->detail('course_fields', HealthStatusEnum::Degraded, implode(', ', $missing), __('formalab::messages.health.course_fields_missing'));
    }
}
