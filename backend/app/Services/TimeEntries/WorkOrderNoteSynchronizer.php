<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\Note;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use App\RichText\RichTextConverter;

/**
 * Keeps a segnatempo's `notes` mirrored as a collaborative note (comment) on
 * its commessa (user decision 2026-09-17, replacing the 2026-09-14 copy into
 * `internal_notes`): created when a note and a commessa are both set,
 * rewritten when the note text changes, deleted when the note is cleared,
 * the commessa changes or is unlinked, or the segnatempo is deleted. The
 * comment's author is the segnatempo's owner, never the acting user.
 *
 * The comment is addressed by `time_entries.work_order_note_id`. It is only
 * touched when the segnatempo's own note or commessa changes, so an edit made
 * in the thread survives unrelated segnatempo saves; a comment deleted from
 * the thread is not resurrected until the segnatempo's note changes again.
 *
 * Written straight through the model, bypassing NoteService: the segnatempo
 * write is already authorized, and the mirrored comment carries no mentions.
 * Callers run it inside the transaction that persists the TimeEntry.
 */
final class WorkOrderNoteSynchronizer
{
    /**
     * Call BEFORE saving $entry: the previous state is read from its
     * original attributes, and the new link is set on it for the save.
     */
    public function sync(TimeEntry $entry): void
    {
        if ($entry->exists && ! $entry->isDirty(['notes', 'work_order_id'])) {
            return;
        }

        $workOrderId = $entry->work_order_id === null ? null : (int) $entry->work_order_id;
        $body = $workOrderId === null ? null : RichTextConverter::plainTextToHtml(trim((string) $entry->notes), false);
        $current = $this->linkedNote($entry);

        if ($current !== null && $body !== null && (int) $current->notable_id === $workOrderId) {
            $current->body = $body;
            $current->save();

            return;
        }

        $current?->delete();
        $entry->work_order_note_id = $body === null ? null : $this->create($workOrderId, (int) $entry->user_id, $body)->id;
    }

    /**
     * Call BEFORE deleting $entry: removes its comment from the commessa.
     */
    public function detach(TimeEntry $entry): void
    {
        $this->linkedNote($entry)?->delete();
    }

    private function linkedNote(TimeEntry $entry): ?Note
    {
        $noteId = $entry->getOriginal('work_order_note_id');

        return $noteId === null ? null : Note::query()->find($noteId);
    }

    private function create(int $workOrderId, int $authorId, string $body): Note
    {
        $note = new Note(['body' => $body]);
        $note->notable_type = (new WorkOrder)->getMorphClass();
        $note->notable_id = $workOrderId;
        $note->user_id = $authorId;
        $note->save();

        return $note;
    }
}
