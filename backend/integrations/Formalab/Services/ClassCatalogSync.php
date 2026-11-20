<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Support\Trigger;
use Throwable;

/**
 * The full catalogue sync: every page of /api/classi is read first and only
 * then written in one transaction, so a Formalab outage leaves the catalogue
 * untouched. Every run is one formalab_sync_runs row; failures are recorded,
 * never thrown.
 */
final class ClassCatalogSync
{
    private const int ERROR_MESSAGE_LIMIT = 1000;

    public function __construct(
        private readonly FormalabClient $client,
        private readonly ClassPayloadMapper $mapper,
        private readonly ClassCatalog $catalog,
        private readonly LinkPropagator $propagator,
        private readonly CourseFieldWriter $courseFields,
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
            $rows = $this->fetchAll($trigger);
            DB::transaction(fn (): int => $this->catalog->upsert($rows));

            // Step 3: push the changed classes to the linked work orders
            $updated = $this->propagator->propagate();

            // Step 4: close the run as a success
            $run->update([
                'outcome' => FormalabSyncRun::OUTCOME_SUCCESS,
                'finished_at' => now(),
                'classes_synced' => count($rows),
                'work_orders_updated' => $updated,
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
     * @return list<array<string, mixed>>
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

        return array_values($rows);
    }
}
