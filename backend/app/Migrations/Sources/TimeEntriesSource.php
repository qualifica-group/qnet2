<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyTaskLookups;
use App\Migrations\Support\LegacyTimeEntryLinks;
use App\Models\TimeEntry;
use App\Models\User;
use RuntimeException;

/**
 * `time-entries` migration source (spec 0341): one legacy segnatempo interval
 * per row, written directly to the model (D-4), not through TimeEntryService,
 * which would check the task's visibility and overlaps and mirror the note on
 * the commessa (U-2). No activity log, no notification.
 *
 * The legacy sends the entry ready (D-2): minutes, type name, a non-blank
 * title, plain-text notes, decoded links; intervals qnet-2 cannot hold arrive
 * as orphans (D-3). What stays here is the qnet-2 side: `old_id` lookups, the
 * link rule (LegacyTimeEntryLinks), the defensive checks of D-6 and the
 * column limits.
 */
class TimeEntriesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    private const int TITLE_MAX = 191;

    private const int NOTES_MAX = 5000;

    private const int MIN_MINUTES = 1;

    private const int MAX_MINUTES = 1440;

    public function __construct(
        ExternalApiClient $client,
        private readonly LegacyTimeEntryLinks $links,
        private readonly LegacyTaskLookups $lookups,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'time-entries';
    }

    public function label(): string
    {
        return 'Time entries';
    }

    public function endpoint(): string
    {
        return 'time-entries';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
            MigrationDependency::optional('registries'),
            MigrationDependency::optional('opportunities'),
            MigrationDependency::optional('work-orders'),
            MigrationDependency::optional('tasks'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'user_id', 'label' => 'User (external id)', 'type' => 'number'],
            ['id' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'type_name', 'label' => 'Type', 'type' => 'string'],
            ['id' => 'start_time', 'label' => 'Start time', 'type' => 'string'],
            ['id' => 'end_time', 'label' => 'End time', 'type' => 'string'],
            ['id' => 'minutes', 'label' => 'Minutes', 'type' => 'number'],
            ['id' => 'work_order_id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'task_id', 'label' => 'Task (external id)', 'type' => 'number'],
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
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record) ?? throw new RuntimeException('External id is required.');

        if ($this->existsByOldId(TimeEntry::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: the owner, the type and the duration; any missing fails the row (D-6).
        $userId = $this->requiredOldId(User::class, $record['user_id'] ?? null, 'User');
        $date = $this->requiredText($record['date'] ?? null, 'date');
        $taskTypeId = $this->taskTypeId($record['type_name'] ?? null);
        $minutes = $this->minutes($record['minutes'] ?? null);

        // Step 2: the record links, imposed by the task when there is one (D-5).
        $links = $this->links->resolve($record, $warnings);

        // Step 3: an owner who already logged time in qnet-2 that day (D-7).
        $this->warnOnSameDayEntries($userId, $date, $warnings);

        // Step 4: write the entry quietly, then the legacy anchors.
        $entry = new TimeEntry([
            'user_id' => $userId,
            'date' => $date,
            'title' => $this->title($links['title'] ?? $record['title'] ?? null, $warnings),
            'task_type_id' => $taskTypeId,
            'start_time' => $this->blankToNull($record['start_time'] ?? null),
            'end_time' => $this->blankToNull($record['end_time'] ?? null),
            'minutes' => $minutes,
            'notes' => $this->cappedText($record['notes'] ?? null, self::NOTES_MAX, 'Notes', $warnings),
            'registry_id' => $links['registry_id'],
            'opportunity_id' => $links['opportunity_id'],
            'work_order_id' => $links['work_order_id'],
            'task_id' => $links['task_id'],
            'work_order_stage_id' => $links['work_order_stage_id'],
        ]);
        $entry->saveQuietly();
        $this->finalizeLegacyRecord($entry, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $entry);
    }

    /**
     * @throws RuntimeException when the type is missing or unknown in qnet-2
     */
    private function taskTypeId(mixed $typeName): int
    {
        $typeName = $this->requiredText($typeName, 'type_name');

        return $this->lookups->typeIdByName($typeName)
            ?? throw new RuntimeException("Task type \"{$typeName}\" not found; the time entry is not imported.");
    }

    /**
     * @throws RuntimeException when the legacy sends a duration qnet-2 cannot hold
     */
    private function minutes(mixed $minutes): int
    {
        $value = filter_var($minutes, FILTER_VALIDATE_INT);

        if ($value === false || $value < self::MIN_MINUTES || $value > self::MAX_MINUTES) {
            throw new RuntimeException('Minutes must be between '.self::MIN_MINUTES.' and '.self::MAX_MINUTES.'; the time entry is not imported.');
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function title(mixed $title, array &$warnings): string
    {
        return $this->cappedText($title, self::TITLE_MAX, 'Title', $warnings)
            ?? throw new RuntimeException('Title is required.');
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function warnOnSameDayEntries(int $userId, string $date, array &$warnings): void
    {
        $hasManualEntries = TimeEntry::query()
            ->where('user_id', $userId)
            ->where('date', $date)
            ->whereNull('old_id')
            ->exists();

        if ($hasManualEntries) {
            $warnings[] = "The user already has time entries logged in QNet on {$date}; imported alongside them.";
        }
    }
}
