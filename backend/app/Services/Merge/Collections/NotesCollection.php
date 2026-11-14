<?php

namespace App\Services\Merge\Collections;

use App\Models\Note;
use App\Services\Merge\MergeItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The notes thread of the records (spec 0394, D-6/D-13). The discardable unit
 * is the root note: replies follow it, whether it is moved or soft-deleted.
 */
final class NotesCollection implements MergeCollection
{
    private const int LABEL_LENGTH = 80;

    public function key(): string
    {
        return 'notes';
    }

    public function items(Collection $records): array
    {
        $recordIds = $records->map(static fn (Model $record): int => (int) $record->getKey())->all();

        return Note::query()
            ->where('notable_type', $records->first()?->getMorphClass())
            ->whereIn('notable_id', $recordIds)
            ->whereNull('parent_id')
            ->orderBy('notable_id')
            ->orderBy('id')
            ->get()
            ->map(static fn (Note $note): MergeItem => new MergeItem(
                id: $note->id,
                recordId: (int) $note->notable_id,
                label: Str::limit(trim(strip_tags((string) $note->body)), self::LABEL_LENGTH),
                detail: $note->created_at?->toDateString(),
                matchKey: null,
            ))
            ->all();
    }

    public function apply(Model $survivor, Collection $merged, array $discardedIds): void
    {
        $type = $survivor->getMorphClass();
        $mergedIds = $merged->map(static fn (Model $record): int => (int) $record->getKey())->all();

        if ($discardedIds !== []) {
            DB::table('notes')
                ->where('notable_type', $type)
                ->whereIn('notable_id', $mergedIds)
                ->whereNull('deleted_at')
                ->where(static fn ($match) => $match->whereIn('id', $discardedIds)->orWhereIn('parent_id', $discardedIds))
                ->update(['deleted_at' => now()]);
        }

        // Deleted notes move too (still soft-deleted): their history stays reachable from the survivor.
        DB::table('notes')
            ->where('notable_type', $type)
            ->whereIn('notable_id', $mergedIds)
            ->update(['notable_id' => $survivor->getKey()]);
    }
}
