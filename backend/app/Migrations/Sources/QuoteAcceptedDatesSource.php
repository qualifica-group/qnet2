<?php

namespace App\Migrations\Sources;

use App\Enums\WorkflowStatusGroup;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * `quote-accepted-dates` migration source (spec 0365, D-7): the acceptance date of the quotes
 * already migrated. The legacy sends `{ id, accepted_at }` (Y-m-d) for every quotation with a
 * date, so here the quote is only looked up by `old_id` and the date written when the quote is
 * today in a won state and has none yet; anything else is skipped, a quote not migrated yet
 * is skipped with a warning. Idempotent: a second run finds the date already there.
 *
 * Written with `saveQuietly` (no `saving` hook, no activity log): it records history, it is
 * not a status change.
 */
class QuoteAcceptedDatesSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'quote-accepted-dates';
    }

    public function label(): string
    {
        return 'Quote accepted dates';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [MigrationDependency::required('quotes')];
    }

    public function endpoint(): string
    {
        return 'quote-accepted-dates';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Quote (external id)', 'type' => 'number'],
            ['id' => 'accepted_at', 'label' => 'Accepted on', 'type' => 'string'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'accepted_at' => is_string($record['accepted_at'] ?? null) ? $record['accepted_at'] : null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        // Step 1: the quote must already be migrated
        $quote = Quote::query()->with('quoteWorkflowStatus')->where('old_id', $externalId)->first();

        if ($quote === null) {
            return MigrationRowOutcome::skipped(["Quote not migrated (legacy id {$externalId}); migrate quotes first."]);
        }

        // Step 2: only a won quote without a date takes one
        if ($quote->accepted_at !== null || $quote->quoteWorkflowStatus?->group !== WorkflowStatusGroup::ClosedWon) {
            return MigrationRowOutcome::skipped();
        }

        // Step 3: a date that is not Y-m-d is reported, never repaired
        $date = $this->parseDate($record['accepted_at'] ?? null);

        if ($date === null) {
            return MigrationRowOutcome::skipped(["Quote {$externalId}: accepted_at is not a valid Y-m-d date."]);
        }

        // Step 4: write quietly
        $quote->forceFill(['accepted_at' => $date])->saveQuietly();

        return MigrationRowOutcome::created();
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }
}
