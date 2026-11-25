<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use Illuminate\Support\Facades\Cache;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Services\FormalabClient;
use Integrations\Formalab\Support\Trigger;

/**
 * The class of an edition WITH its learners, read live from Formalab
 * (`GET /api/classi/{id}`) and kept in cache like the legacy
 * `FormalabService::getClasse` (spec 0427): a failed read is never cached, so
 * a temporary outage does not stay frozen. Learners come sorted by last name,
 * then first name, case-insensitive.
 */
final class ClassRoster
{
    private const string CACHE_PREFIX = 'formalab.learner_statement.class.';

    public function __construct(private readonly FormalabClient $client) {}

    /**
     * @return array<string, mixed>|null the `classe` payload; null when Formalab cannot give it
     */
    public function get(int $editionId, bool $refresh = false): ?array
    {
        $key = self::CACHE_PREFIX.$editionId;

        if ($refresh) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $class = $this->fetch($editionId);

        if ($class !== null) {
            Cache::put($key, $class, now()->addMinutes((int) config('formalab.learner_statement.cache_minutes')));
        }

        return $class;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(int $editionId): ?array
    {
        try {
            $body = $this->client->get('/api/classi/'.$editionId, [], Trigger::WorkOrder);
        } catch (FormalabException) {
            // Already recorded in formalab_call_logs; the prospetto shows "class not available".
            return null;
        }

        $class = $body['classe'] ?? null;

        if (! is_array($class)) {
            return null;
        }

        $learners = is_array($class['discenti'] ?? null) ? array_values($class['discenti']) : [];
        usort($learners, static function (array $a, array $b): int {
            $byLastName = strcasecmp((string) ($a['last_name'] ?? ''), (string) ($b['last_name'] ?? ''));

            return $byLastName !== 0 ? $byLastName : strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        $class['discenti'] = $learners;

        return $class;
    }
}
