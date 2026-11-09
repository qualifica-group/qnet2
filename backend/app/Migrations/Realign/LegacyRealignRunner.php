<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Migrations\Realign\Targets\QuotesTarget;
use App\Migrations\Support\LegacyRecordTouchGuard;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Aligns one module (spec 0375). Simulation and `--apply` run the same code: every
 * record goes through its own transaction, and only the outcome differs (commit or
 * rollback), so what the simulation reports is what the apply writes.
 */
final class LegacyRealignRunner
{
    private const string ERROR_LEVEL = 'error';

    private const string WARNING_LEVEL = 'warning';

    public function __construct(
        private readonly LegacyRealignClient $client,
        private readonly LegacyRealignTouchGuard $touchGuard,
    ) {}

    public function run(LegacyRealignTarget $target, string $since, bool $apply, LegacyRealignReport $report): void
    {
        // Offer lines are aligned only when changed since the date (D-12).
        if ($target instanceof QuotesTarget) {
            $target->changedSince($since);
        }

        // Step 1: what the legacy says changed or disappeared.
        $changes = $this->client->changes($target->source(), $since);
        $report->count($target->source(), LegacyRealignReport::CHANGED_IN_LEGACY, count($changes['changed']));

        // Step 2: a record deleted in the legacy stays here and is only listed (D-2).
        $this->listDeleted($target, $changes['deleted'], $report);

        // Step 3: only what is already on qnet-2, one page of legacy records at a time (D-5).
        foreach (array_chunk($changes['changed'], LegacyRealignClient::MAX_IDS_PER_REQUEST) as $legacyKeys) {
            $this->alignPage($target, $legacyKeys, $apply, $report);
        }
    }

    /**
     * @param  list<string>  $legacyKeys
     */
    private function listDeleted(LegacyRealignTarget $target, array $legacyKeys, LegacyRealignReport $report): void
    {
        foreach (array_chunk($legacyKeys, LegacyRealignClient::MAX_IDS_PER_REQUEST) as $page) {
            foreach ($target->findMany($page) as $legacyKey => $record) {
                $report->count($target->source(), LegacyRealignReport::DELETED_IN_LEGACY);
                $report->deleted[] = [$target->source(), $legacyKey, $record->getKey(), $target->label($record), null];
            }
        }
    }

    /**
     * @param  list<string>  $legacyKeys
     */
    private function alignPage(LegacyRealignTarget $target, array $legacyKeys, bool $apply, LegacyRealignReport $report): void
    {
        $records = $target->findMany($legacyKeys);
        $report->count($target->source(), LegacyRealignReport::NOT_ON_QNET2, count($legacyKeys) - count($records));

        if ($records === []) {
            return;
        }

        foreach ($this->client->records($target->endpoint(), array_map(strval(...), array_keys($records))) as $legacy) {
            $record = $records[$target->legacyKey($legacy)] ?? null;

            if ($record !== null) {
                $this->alignRecord($target, $record, $legacy, $apply, $report);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function alignRecord(LegacyRealignTarget $target, Model $record, array $legacy, bool $apply, LegacyRealignReport $report): void
    {
        $module = $target->source();
        $legacyKey = $target->legacyKey($legacy);

        // Step 1: a record the legacy could not send as data fails with its reason.
        if (filled($legacy['orphan_reason'] ?? null)) {
            $this->fail($report, $module, $legacyKey, (string) $legacy['orphan_reason']);

            return;
        }

        // Step 2: re-read right before writing, so a change made a moment ago still wins (D-1).
        $lastChange = $this->touchGuard->lastChange($target->touchSubjects($record));

        // Step 3: compute the difference; only an untouched record in apply mode commits.
        try {
            [$differences, $notes] = $this->attempt($target, $record, $legacy, $apply && $lastChange === null);
        } catch (Throwable $exception) {
            $this->fail($report, $module, $legacyKey, $exception->getMessage());

            return;
        }

        // Step 4: put the outcome in the report.
        $this->record($target, $record, $legacyKey, $differences, $notes, $lastChange, $report);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array{0: array<string, array{0: mixed, 1: mixed}>, 1: LegacyRealignNotes}
     */
    private function attempt(LegacyRealignTarget $target, Model $record, array $legacy, bool $persist): array
    {
        return AssignmentNotifier::muted(fn (): array => activity()->withoutLogs(
            fn (): array => LegacyRecordTouchGuard::ignoring(fn (): array => $this->inTransaction($target, $record, $legacy, $persist)),
        ));
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array{0: array<string, array{0: mixed, 1: mixed}>, 1: LegacyRealignNotes}
     */
    private function inTransaction(LegacyRealignTarget $target, Model $record, array $legacy, bool $persist): array
    {
        DB::beginTransaction();

        try {
            $notes = new LegacyRealignNotes;
            $before = $target->snapshot($target->reload($record));
            $target->realign($target->reload($record), $legacy, $notes);
            $differences = $this->differences($before, $target->snapshot($target->reload($record)));

            $persist ? DB::commit() : DB::rollBack();

            return [$differences, $notes];
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $before
     * @param  array<string, string|int|float|bool|null>  $after
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function differences(array $before, array $after): array
    {
        $differences = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $differences[$field] = [$before[$field] ?? null, $value];
            }
        }

        return $differences;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $differences
     */
    private function record(LegacyRealignTarget $target, Model $record, string $legacyKey, array $differences, LegacyRealignNotes $notes, ?Activity $lastChange, LegacyRealignReport $report): void
    {
        $module = $target->source();
        $label = $target->label($record);

        foreach ($notes->warnings as $warning) {
            $report->message($module, $legacyKey, self::WARNING_LEVEL, $warning);
        }

        if ($lastChange !== null) {
            $report->count($module, LegacyRealignReport::SKIPPED_TOUCHED);
            $this->listTouched($module, $legacyKey, $record, $label, $differences, $lastChange, $report);

            return;
        }

        $report->count($module, $differences === [] ? LegacyRealignReport::UNCHANGED : LegacyRealignReport::ALIGNED);

        foreach ($differences as $field => [$current, $legacy]) {
            $report->changes[] = [$module, $legacyKey, $record->getKey(), $label, $field, $current, $legacy];
            $report->amountChanges += $target->isAmountField($field) ? [$label => true] : [];
        }

        $report->configurations += array_fill_keys($notes->configurations, true);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $differences
     */
    private function listTouched(string $module, string $legacyKey, Model $record, string $label, array $differences, Activity $lastChange, LegacyRealignReport $report): void
    {
        $head = [$module, $legacyKey, $record->getKey(), $label, (string) $lastChange->created_at, $this->author($lastChange)];

        if ($differences === []) {
            $report->touched[] = [...$head, null, null, null];
        }

        foreach ($differences as $field => [$current, $legacy]) {
            $report->touched[] = [...$head, $field, $current, $legacy];
        }
    }

    private function author(Activity $activity): ?string
    {
        return $activity->causer_id === null ? null : User::query()->whereKey($activity->causer_id)->value('name');
    }

    private function fail(LegacyRealignReport $report, string $module, string $legacyKey, string $message): void
    {
        $report->count($module, LegacyRealignReport::FAILED);
        $report->message($module, $legacyKey, self::ERROR_LEVEL, $message);
    }
}
