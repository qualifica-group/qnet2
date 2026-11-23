<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Support\Trigger;

/**
 * The local copy of the Formalab class catalogue: bulk upsert by edition_id
 * and the live read of a single edition.
 */
final class ClassCatalog
{
    private const int UPSERT_CHUNK = 200;

    public function __construct(
        private readonly FormalabClient $client,
        private readonly ClassPayloadMapper $mapper,
    ) {}

    /**
     * Idempotent write of already-mapped rows; returns how many were written.
     *
     * @param  list<array<string, mixed>>  $rows  ClassPayloadMapper::map() output
     */
    public function upsert(array $rows): int
    {
        $now = now();
        $columns = array_keys($rows[0] ?? []);
        $updatable = array_values(array_diff($columns, ['id', 'edition_id']));

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            FormalabClass::query()->upsert(
                array_map(static fn (array $row): array => $row + ['last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk),
                ['edition_id'],
                [...$updatable, 'last_seen_at', 'updated_at'],
            );
        }

        return count($rows);
    }

    /**
     * Reads one edition live from Formalab and stores it.
     *
     * @return FormalabClass|null null when Formalab has no such edition
     *
     * @throws FormalabException Formalab not reachable or answering with an error
     */
    public function refresh(int $editionId, Trigger $trigger): ?FormalabClass
    {
        try {
            $body = $this->client->get('/api/classi/'.$editionId, [], $trigger);
        } catch (FormalabException $exception) {
            if ($exception->isNotFound()) {
                return null;
            }

            throw $exception;
        }

        $row = is_array($body['classe'] ?? null) ? $this->mapper->map($body['classe']) : null;

        if ($row === null) {
            return null;
        }

        $this->upsert([$row]);

        return FormalabClass::query()->where('edition_id', $editionId)->first();
    }
}
