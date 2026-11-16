<?php

namespace App\Services\Merge\Collections;

use App\Models\Attachment;
use App\Services\Merge\MergeItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The documents (attachments) of the records (spec 0394, D-6/D-13). The
 * discarded ones stay on the duplicate and are removed with it by the
 * `HasAttachments` cascade (file included).
 */
final class DocumentsCollection implements MergeCollection
{
    public function key(): string
    {
        return 'documents';
    }

    public function items(Collection $records): array
    {
        $recordIds = $records->map(static fn (Model $record): int => (int) $record->getKey())->all();

        return Attachment::query()
            ->where('attachable_type', $records->first()?->getMorphClass())
            ->whereIn('attachable_id', $recordIds)
            ->orderBy('attachable_id')
            ->orderBy('id')
            ->get()
            ->map(static fn (Attachment $attachment): MergeItem => new MergeItem(
                id: $attachment->id,
                recordId: (int) $attachment->attachable_id,
                label: (string) $attachment->original_name,
                detail: $attachment->collection,
                matchKey: null,
            ))
            ->all();
    }

    public function apply(Model $survivor, Collection $merged, array $discardedIds): void
    {
        DB::table('attachments')
            ->where('attachable_type', $survivor->getMorphClass())
            ->whereIn('attachable_id', $merged->map(static fn (Model $record): int => (int) $record->getKey())->all())
            ->when($discardedIds !== [], static fn ($query) => $query->whereNotIn('id', $discardedIds))
            ->update(['attachable_id' => $survivor->getKey()]);
    }
}
