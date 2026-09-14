<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\UpdateDayNoteData;
use App\Models\TimeEntryDayNote;
use App\Models\User;

/**
 * Business logic for PUT /api/time-entries/day-notes (spec 0122,
 * data_contract, AC-021). One free-text note per user per date: an
 * empty/blank note (after trim) DELETES the row rather than persisting a
 * blank string, so `time_entry_day_notes` never accumulates "" rows —
 * `TimeEntryDayNoteFactory`/the D-4 model docblock assume the same.
 */
final class TimeEntryDayNoteService
{
    /**
     * @return array{date: string, note: string|null}
     */
    public function upsert(UpdateDayNoteData $data, User $owner): array
    {
        $note = trim((string) $data->note);

        if ($note === '') {
            TimeEntryDayNote::query()
                ->where('user_id', $owner->id)
                ->where('date', $data->date)
                ->delete();

            return ['date' => $data->date, 'note' => null];
        }

        TimeEntryDayNote::query()->updateOrCreate(
            ['user_id' => $owner->id, 'date' => $data->date],
            ['note' => $note],
        );

        return ['date' => $data->date, 'note' => $note];
    }
}
