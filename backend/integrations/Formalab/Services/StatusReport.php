<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

/**
 * The read model of the Formalab status page. Never exposes credentials,
 * only whether they are configured, and the base URL without path.
 */
final class StatusReport
{
    public function __construct(
        private readonly CourseFieldWriter $courseFields,
        private readonly LinkBackfill $backfill,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $since = now()->subDay();
        $calls = FormalabCallLog::query()->where('created_at', '>=', $since);

        return [
            'base_url' => $this->origin((string) config('formalab.url')),
            'credentials_configured' => (string) config('formalab.user_email') !== '' && (string) config('formalab.user_password') !== '',
            'inbound_enabled' => (string) config('formalab.inbound_token') !== '',
            'sync_interval_minutes' => (int) config('formalab.sync_interval_minutes'),
            'last_sync' => $this->lastSync(),
            'classes_count' => FormalabClass::query()->count(),
            'linked_work_orders_count' => FormalabWorkOrderLink::query()->count(),
            'unlinked_work_orders' => $this->backfill->pending(),
            'missing_course_fields' => $this->courseFields->missingCodes(),
            'calls_last_24h' => [
                'total' => (clone $calls)->count(),
                'failures' => (clone $calls)->where('outcome', FormalabCallLog::OUTCOME_FAILURE)->count(),
                'inbound' => (clone $calls)->where('direction', FormalabCallLog::DIRECTION_INBOUND)->count(),
                'outbound' => (clone $calls)->where('direction', FormalabCallLog::DIRECTION_OUTBOUND)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastSync(): ?array
    {
        $run = FormalabSyncRun::query()->latest('started_at')->latest('id')->first();

        return $run === null ? null : [
            'id' => $run->id,
            'trigger' => $run->trigger,
            'outcome' => $run->outcome,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'classes_synced' => $run->classes_synced,
            'work_orders_updated' => $run->work_orders_updated,
            'classes_removed' => $run->classes_removed,
            'error_message' => $run->error_message,
            'warning_message' => $run->warning_message,
        ];
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'] : null;
    }
}
