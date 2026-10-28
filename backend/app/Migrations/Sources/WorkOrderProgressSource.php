<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * `work-order-progress` migration source (spec 0321, D-10): the manual progress
 * entries of a migrated work order. The legacy sends one record per order with
 * the entries already cumulated (`percentage` 1..100, `recorded_on` Y-m-d), so
 * here they are only looked up and written.
 *
 * Entries are only ever CREATED: a work order that already has some (a re-run,
 * or entries typed in qnet-2) is skipped untouched; a work order not migrated
 * fails its row. An invalid entry list fails the row and nothing is written.
 * Written without activity log, created_by null.
 */
class WorkOrderProgressSource extends AbstractMigrationSource
{
    private const int MIN_PERCENTAGE = 1;

    private const int MAX_PERCENTAGE = 100;

    public function key(): string
    {
        return 'work-order-progress';
    }

    public function label(): string
    {
        return 'Work order progress';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [MigrationDependency::required('work-orders')];
    }

    public function endpoint(): string
    {
        return 'work-order-progress';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'entries_count', 'label' => 'Entries', 'type' => 'number'],
            ['id' => 'latest_percentage', 'label' => 'Latest percentage', 'type' => 'number'],
            ['id' => 'latest_recorded_on', 'label' => 'Latest date', 'type' => 'string'],
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
        $entries = is_array($record['entries'] ?? null) ? $record['entries'] : [];
        $latest = $entries === [] ? [] : (array) end($entries);

        return [
            'id' => $record['id'] ?? null,
            'entries_count' => count($entries),
            'latest_percentage' => $latest['percentage'] ?? null,
            'latest_recorded_on' => $latest['recorded_on'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        // Step 1: the work order must already be migrated
        $workOrder = WorkOrder::query()->where('old_id', $externalId)->first()
            ?? throw new RuntimeException("Work order not migrated (legacy id {$externalId}); migrate work orders first.");

        // Step 2: never touch a work order that already has entries
        if ($workOrder->progressEntries()->exists()) {
            return MigrationRowOutcome::skipped();
        }

        // Step 3: validate the whole list before writing anything
        $entries = $this->validatedEntries($record['entries'] ?? null);

        // Step 4: write them all or none, without activity log
        activity()->withoutLogs(function () use ($workOrder, $entries): void {
            DB::transaction(function () use ($workOrder, $entries): void {
                foreach ($entries as $entry) {
                    WorkOrderProgressEntry::query()->create($entry + ['work_order_id' => $workOrder->id, 'created_by' => null]);
                }
            });
        });

        return MigrationRowOutcome::created();
    }

    /**
     * @return list<array{percentage: int, recorded_on: string}>
     */
    private function validatedEntries(mixed $entries): array
    {
        if (! is_array($entries) || $entries === []) {
            throw new RuntimeException('No progress entries received.');
        }

        $validated = [];
        $previousPercentage = 0;
        $previousDate = null;

        foreach (array_values($entries) as $position => $entry) {
            $number = $position + 1;
            $percentage = is_array($entry) ? ($entry['percentage'] ?? null) : null;

            if (! is_int($percentage) || $percentage < self::MIN_PERCENTAGE || $percentage > self::MAX_PERCENTAGE) {
                throw new RuntimeException("Entry {$number}: percentage must be an integer between ".self::MIN_PERCENTAGE.' and '.self::MAX_PERCENTAGE.'.');
            }

            if ($percentage <= $previousPercentage) {
                throw new RuntimeException("Entry {$number}: percentage {$percentage} does not increase the previous one ({$previousPercentage}).");
            }

            $date = $this->parseDate($entry['recorded_on'] ?? null)
                ?? throw new RuntimeException("Entry {$number}: recorded_on must be a valid Y-m-d date.");

            if ($previousDate !== null && $date < $previousDate) {
                throw new RuntimeException("Entry {$number}: recorded_on {$date} is before the previous entry ({$previousDate}).");
            }

            $validated[] = ['percentage' => $percentage, 'recorded_on' => $date];
            $previousPercentage = $percentage;
            $previousDate = $date;
        }

        return $validated;
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
