<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Support\Trigger;
use Throwable;

/**
 * The full catalogue sync: every page of /api/classi is read first and only
 * then written in one transaction, so a Formalab outage leaves the catalogue
 * untouched. The classes Formalab no longer lists are removed with their
 * links (spec 0426 D-12), then the work orders not linked yet whose class
 * select holds a catalogued edition are linked (spec 0423 D-15, 0426), and
 * every changed or new link gets the class fields. Every run is one formalab_sync_runs row; failures are
 * recorded, never thrown.
 */
final class ClassCatalogSync
{
    private const int ERROR_MESSAGE_LIMIT = 1000;

    public function __construct(
        private readonly FormalabClient $client,
        private readonly ClassPayloadMapper $mapper,
        private readonly ClassCatalog $catalog,
        private readonly LinkBackfill $backfill,
        private readonly LinkPropagator $propagator,
        private readonly CourseFieldWriter $courseFields,
        private readonly RemovedClassCleanup $cleanup,
    ) {}

    public function handle(Trigger $trigger): FormalabSyncRun
    {
        // Step 1: open the run
        $run = FormalabSyncRun::query()->create([
            'trigger' => $trigger->value,
            'outcome' => FormalabSyncRun::OUTCOME_RUNNING,
            'started_at' => now(),
        ]);

        try {
            // Step 2: read every page, then write the catalogue atomically
            ['rows' => $rows, 'complete' => $complete] = $this->fetchAll($trigger);
            DB::transaction(fn (): int => $this->catalog->upsert($rows));

            // Step 3: classes Formalab no longer lists are removed, with their links (only on a complete, non-empty read)
            $removed = $complete && $rows !== [] ? $this->removeMissing($rows) : 0;

            // Step 4: link the work orders whose class select now holds a catalogued edition
            $this->backfill->link();

            // Step 5: push the changed classes, and the new links, to the work orders
            $updated = $this->propagator->propagate();

            // Step 6: close the run as a success
            $run->update([
                'outcome' => FormalabSyncRun::OUTCOME_SUCCESS,
                'finished_at' => now(),
                'classes_synced' => count($rows),
                'work_orders_updated' => $updated,
                'classes_removed' => $removed,
                'warning_message' => $this->missingFieldsWarning(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'outcome' => FormalabSyncRun::OUTCOME_FAILURE,
                'finished_at' => now(),
                'error_message' => Str::limit($exception->getMessage(), self::ERROR_MESSAGE_LIMIT, ''),
            ]);
        }

        return $run->refresh();
    }

    /**
     * Records in the run the course fields that no longer exist, so the
     * history shows since when they stopped updating.
     */
    private function missingFieldsWarning(): ?string
    {
        $missing = $this->courseFields->missingCodes();

        return $missing === [] ? null : __('formalab::messages.course_fields_missing', ['codes' => implode(', ', $missing)]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows  the classes Formalab listed
     * @return int classes removed
     */
    private function removeMissing(array $rows): int
    {
        $missing = FormalabClass::query()
            ->whereNotIn('edition_id', array_column($rows, 'edition_id'))
            ->pluck('edition_id')
            ->map(intval(...))
            ->all();

        return $missing === [] ? 0 : $this->cleanup->handle($missing)['classes'];
    }

    /**
     * `complete` is false when the page cap stopped the read before the last
     * page: what was not read is unknown, never removed.
     *
     * @return array{rows: list<array<string, mixed>>, complete: bool}
     *
     * @throws FormalabException
     */
    private function fetchAll(Trigger $trigger): array
    {
        $rows = [];
        $page = 1;
        $lastPage = 1;

        do {
            $body = $this->client->get('/api/classi', ['per_page' => (int) config('formalab.page_size'), 'page' => $page], $trigger);

            foreach ($body['classi'] ?? [] as $payload) {
                $row = is_array($payload) ? $this->mapper->map($payload) : null;

                if ($row !== null) {
                    $rows[$row['edition_id']] = $row;
                }
            }

            $lastPage = max(1, (int) ($body['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= (int) config('formalab.max_pages'));

        return ['rows' => array_values($rows), 'complete' => $page > $lastPage];
    }
}
