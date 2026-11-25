<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Integrations\Formalab\Jobs\SyncFormalabClasses;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Support\Trigger;

/**
 * The only way a catalogue sync starts (button, schedule, console): one at a
 * time. The run row is opened when the sync is requested, so "running" covers
 * both the wait in the queue and the execution, and a second request is
 * refused until it closes. A run its worker never closed (process killed by
 * a deploy, worker down) stops blocking after the sync timeout plus a margin,
 * and is closed as interrupted by the next request.
 */
final class CatalogSyncLauncher
{
    /** Serialises the check-then-open, so two requests in the same instant cannot both open a run. */
    private const string OPEN_LOCK = 'formalab:catalog-sync:open';

    private const int OPEN_LOCK_SECONDS = 10;

    private const int OPEN_LOCK_WAIT_SECONDS = 5;

    /** Beyond the sync timeout, the time a run may wait in the queue before it counts as abandoned. */
    private const int STALE_MARGIN_SECONDS = 300;

    private const array CATALOGUE_TRIGGERS = [Trigger::Schedule->value, Trigger::Manual->value];

    /**
     * Opens the run and queues the sync on the Formalab queue.
     *
     * @return FormalabSyncRun|null null: a sync is already running
     */
    public function queue(Trigger $trigger): ?FormalabSyncRun
    {
        $run = $this->open($trigger);

        if ($run !== null) {
            SyncFormalabClasses::dispatch($run->id);
        }

        return $run;
    }

    /**
     * Opens the run of a new sync, unless one is already running.
     *
     * @return FormalabSyncRun|null null: a sync is already running
     */
    public function open(Trigger $trigger): ?FormalabSyncRun
    {
        try {
            return Cache::lock(self::OPEN_LOCK, self::OPEN_LOCK_SECONDS)->block(self::OPEN_LOCK_WAIT_SECONDS, function () use ($trigger): ?FormalabSyncRun {
                // Step 1: a run its worker never closed stops blocking
                $this->closeAbandoned();

                // Step 2: one catalogue sync at a time
                if ($this->inProgress()) {
                    return null;
                }

                // Step 3: open the run
                return FormalabSyncRun::query()->create([
                    'trigger' => $trigger->value,
                    'outcome' => FormalabSyncRun::OUTCOME_RUNNING,
                    'started_at' => now(),
                ]);
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    public function inProgress(): bool
    {
        return $this->running()->where('started_at', '>=', $this->abandonedBefore())->exists();
    }

    /**
     * Closes as interrupted the given run, if still open (the job failed or timed out).
     */
    public function interrupt(int $runId): void
    {
        $this->running()->whereKey($runId)->update($this->interruptedAttributes());
    }

    private function closeAbandoned(): void
    {
        $this->running()->where('started_at', '<', $this->abandonedBefore())->update($this->interruptedAttributes());
    }

    /**
     * @return Builder<FormalabSyncRun>
     */
    private function running(): Builder
    {
        return FormalabSyncRun::query()
            ->whereIn('trigger', self::CATALOGUE_TRIGGERS)
            ->where('outcome', FormalabSyncRun::OUTCOME_RUNNING);
    }

    private function abandonedBefore(): DateTimeInterface
    {
        return now()->subSeconds((int) config('formalab.sync_timeout_seconds') + self::STALE_MARGIN_SECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    private function interruptedAttributes(): array
    {
        return [
            'outcome' => FormalabSyncRun::OUTCOME_FAILURE,
            'finished_at' => now(),
            'error_message' => __('formalab::messages.sync_interrupted'),
        ];
    }
}
