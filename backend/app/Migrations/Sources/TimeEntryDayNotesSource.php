<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Models\TimeEntryDayNote;
use App\Models\User;
use RuntimeException;

/**
 * `time-entry-day-notes` migration source (spec 0341 D-8): the legacy day
 * notes of the segnatempo, sent as plain text. qnet-2 keeps one note per user
 * and date: a note written by hand in qnet-2 for that day is adopted (it gets
 * the `old_id`, its text stays) instead of being duplicated.
 */
class TimeEntryDayNotesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    private const int NOTE_MAX = 5000;

    public function key(): string
    {
        return 'time-entry-day-notes';
    }

    public function label(): string
    {
        return 'Time entry day notes';
    }

    public function endpoint(): string
    {
        return 'time-entry-day-notes';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
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
            ['id' => 'note', 'label' => 'Note', 'type' => 'string'],
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

        if ($this->existsByOldId(TimeEntryDayNote::class, $externalId)) {
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

        // Step 1: the owner and the day; a user not migrated fails the row.
        $userId = $this->requiredOldId(User::class, $record['user_id'] ?? null, 'User');
        $date = $this->requiredText($record['date'] ?? null, 'date');

        // Step 2: adopt the note already written in qnet-2 for that day, or write the legacy one.
        $note = TimeEntryDayNote::query()->where('user_id', $userId)->where('date', $date)->first();

        if ($note !== null) {
            $warnings[] = "A day note for {$date} already exists in QNet; it was linked to the legacy note and kept as is.";
            $note->forceFill(['old_id' => $externalId])->saveQuietly();

            return MigrationRowOutcome::created($warnings);
        }

        $note = new TimeEntryDayNote([
            'user_id' => $userId,
            'date' => $date,
            'note' => $this->cappedText($this->requiredText($record['note'] ?? null, 'note'), self::NOTE_MAX, 'Note', $warnings),
        ]);
        $note->saveQuietly();

        // Step 3: the legacy anchors.
        $this->finalizeLegacyRecord($note, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $note);
    }
}
