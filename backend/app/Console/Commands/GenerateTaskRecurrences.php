<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Services\Tasks\TaskOccurrenceFactory;
use App\Services\Tasks\TaskRecurrenceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The scheduler entry point of the recurrence module (spec 0120, D-8/D-9):
 * for every active `task_recurrences` row (or the single one named by
 * `--recurrence`), materializes the occurrences whose calculated `end_date`
 * falls within today, in chronological order, skipping any date already
 * materialized (idempotent — a second run touches nothing, AC-016).
 *
 * `--dry-run` reports what WOULD be created without writing anything.
 * Registered in routes/console.php with `Schedule::command(...)->dailyAt(
 * '01:00')->withoutOverlapping()` (D-8).
 */
class GenerateTaskRecurrences extends Command
{
    protected $signature = 'tasks:generate-recurrences {--recurrence= : limita a una sola serie} {--dry-run}';

    protected $description = 'Materialize the Task occurrences due for every active recurrence series';

    public function __construct(
        private readonly TaskRecurrenceCalculator $calculator,
        private readonly TaskOccurrenceFactory $factory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Step 1: today, in the app's own timezone (D-15) — the horizon
        // every series is materialized up to and including.
        $dryRun = (bool) $this->option('dry-run');
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        // Step 2: the target set — one series, or every one that exists.
        $recurrenceId = $this->option('recurrence');
        $recurrences = TaskRecurrence::query()
            ->when($recurrenceId !== null, fn ($query) => $query->whereKey($recurrenceId))
            ->get();

        // Step 3: one series at a time, one occurrence transaction at a time
        // (constraints): a malformed series never blocks the others.
        $materialized = 0;

        foreach ($recurrences as $recurrence) {
            $materialized += $this->processRecurrence($recurrence, $today, $dryRun);
        }

        $this->info($dryRun
            ? "{$materialized} occurrence(s) would be materialized."
            : "{$materialized} occurrence(s) materialized.");

        return self::SUCCESS;
    }

    /**
     * The capostipite (D-4) is the OLDEST Task on the series — the only one
     * that existed before the series did, and the fixed template D-6 copies
     * from on every occurrence, never a chained copy of the most recent one.
     * A series with no Task at all, or whose capostipite lost its own
     * `end_date` somehow, is skipped rather than fatal — it cannot anchor a
     * calculation.
     */
    private function processRecurrence(TaskRecurrence $recurrence, CarbonImmutable $today, bool $dryRun): int
    {
        $originator = $recurrence->tasks()->oldest('id')->first();

        if ($originator === null || $originator->end_date === null) {
            return 0;
        }

        $rule = TaskRecurrenceData::fromModel($recurrence);
        $from = $recurrence->generated_until !== null
            ? CarbonImmutable::parse($recurrence->generated_until)
            : CarbonImmutable::parse($originator->end_date);

        $dueDates = $this->calculator->nextDates(
            $rule,
            $from,
            limit: PHP_INT_MAX,
            alreadyGenerated: $recurrence->tasks()->count(),
            horizon: $today,
        );

        $count = 0;

        foreach ($dueDates as $endDate) {
            if ($this->materializeOccurrence($recurrence, $originator, $endDate, $dryRun)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * D-9's idempotency belt-and-braces: an existence check first (cheap,
     * avoids a caught exception on the common "already there" path), the
     * UNIQUE(task_recurrence_id, end_date) constraint behind it regardless.
     * The write — factory materialize() + `generated_until` bump — is ONE
     * transaction per occurrence (constraints), so a failure here never
     * rolls back a sibling date already committed earlier in this run.
     */
    private function materializeOccurrence(TaskRecurrence $recurrence, Task $originator, CarbonImmutable $endDate, bool $dryRun): bool
    {
        $exists = $recurrence->tasks()->whereDate('end_date', $endDate->toDateString())->exists();

        if ($exists) {
            return false;
        }

        if ($dryRun) {
            $this->line("  #{$recurrence->id} -> {$endDate->toDateString()}");

            return true;
        }

        DB::transaction(function () use ($recurrence, $originator, $endDate): void {
            $this->factory->materialize($originator, $endDate);
            $recurrence->generated_until = $endDate->toDateString();
            $recurrence->save();
        });

        return true;
    }
}
