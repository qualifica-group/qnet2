<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Illuminate\Support\Str;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Support\Trigger;
use Throwable;

/**
 * Re-reads the given editions live and pushes them to the linked work orders
 * (the inbound webhook). Recorded as a formalab_sync_runs row.
 */
final class EditionRefresher
{
    public function __construct(
        private readonly ClassCatalog $catalog,
        private readonly LinkPropagator $propagator,
    ) {}

    /**
     * @param  list<int>  $editionIds
     * @return array{refreshed: int, work_orders_updated: int}
     *
     * @throws FormalabException Formalab not reachable; the run is recorded as failed
     */
    public function handle(array $editionIds, Trigger $trigger): array
    {
        $run = FormalabSyncRun::query()->create([
            'trigger' => $trigger->value,
            'outcome' => FormalabSyncRun::OUTCOME_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $refreshed = [];

            foreach ($editionIds as $editionId) {
                if ($this->catalog->refresh($editionId, $trigger) !== null) {
                    $refreshed[] = $editionId;
                }
            }

            $updated = $this->propagator->propagate($refreshed);
        } catch (Throwable $exception) {
            $run->update([
                'outcome' => FormalabSyncRun::OUTCOME_FAILURE,
                'finished_at' => now(),
                'error_message' => Str::limit($exception->getMessage(), 1000, ''),
            ]);

            throw $exception;
        }

        $run->update([
            'outcome' => FormalabSyncRun::OUTCOME_SUCCESS,
            'finished_at' => now(),
            'classes_synced' => count($refreshed),
            'work_orders_updated' => $updated,
        ]);

        return ['refreshed' => count($refreshed), 'work_orders_updated' => $updated];
    }
}
