<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\TimeEntry;
use App\Models\WorkOrder;

/**
 * Keeps a segnatempo's `notes` mirrored inside its commessa's
 * `internal_notes` (user decision 2026-09-14): the plain text is appended as
 * its own block, replaced in place when the note changes, and removed when
 * the note is cleared, the commessa changes or the segnatempo is deleted.
 *
 * `internal_notes` stays free text editable by hand, so the copy is located
 * through the snapshot `time_entries.work_order_note` and only matched as a
 * WHOLE block (delimited by BLOCK_SEPARATOR or the text edges), never as a
 * substring of other text. A block the user has edited by hand no longer
 * matches: its replacement is appended and the edited text is left alone,
 * so a manual edit is never overwritten.
 *
 * Callers run it inside the transaction that persists the TimeEntry.
 */
final class WorkOrderNoteSynchronizer
{
    private const string BLOCK_SEPARATOR = "\n\n";

    /**
     * Call BEFORE saving $entry: the previous state is read from its
     * original attributes, and the new snapshot is set on it for the save.
     */
    public function sync(TimeEntry $entry): void
    {
        $previousWorkOrderId = $this->workOrderId($entry->getOriginal('work_order_id'));
        $previousNote = $entry->getOriginal('work_order_note');
        $nextWorkOrderId = $this->workOrderId($entry->work_order_id);
        $nextNote = $nextWorkOrderId === null ? null : $this->normalizedNote($entry->notes);

        if ($previousWorkOrderId === $nextWorkOrderId && $previousNote === $nextNote) {
            return;
        }

        if ($previousWorkOrderId !== null && $previousWorkOrderId === $nextWorkOrderId) {
            $this->rewrite($nextWorkOrderId, $previousNote, $nextNote);
        } else {
            $this->rewrite($previousWorkOrderId, $previousNote, null);
            $this->rewrite($nextWorkOrderId, null, $nextNote);
        }

        $entry->work_order_note = $nextNote;
    }

    /**
     * Call BEFORE deleting $entry: removes its copy from the commessa.
     */
    public function detach(TimeEntry $entry): void
    {
        $this->rewrite($this->workOrderId($entry->work_order_id), $entry->work_order_note, null);
    }

    private function rewrite(?int $workOrderId, ?string $oldBlock, ?string $newBlock): void
    {
        if ($workOrderId === null || ($oldBlock === null && $newBlock === null)) {
            return;
        }

        $workOrder = WorkOrder::query()->lockForUpdate()->find($workOrderId);

        if ($workOrder === null) {
            return;
        }

        $current = $workOrder->internal_notes ?? '';
        $updated = $this->replaceBlock($current, $oldBlock, $newBlock);

        if ($updated === $current) {
            return;
        }

        $workOrder->internal_notes = $updated === '' ? null : $updated;
        $workOrder->save();
    }

    private function replaceBlock(string $text, ?string $oldBlock, ?string $newBlock): string
    {
        $position = $oldBlock === null ? null : $this->findBlock($text, $oldBlock);

        if ($position === null) {
            return $newBlock === null ? $text : $this->appendBlock($text, $newBlock);
        }

        if ($newBlock !== null) {
            return substr_replace($text, $newBlock, $position, strlen($oldBlock));
        }

        return $this->removeBlock($text, $position, strlen($oldBlock));
    }

    private function appendBlock(string $text, string $block): string
    {
        $trimmed = rtrim($text);

        return $trimmed === '' ? $block : $trimmed.self::BLOCK_SEPARATOR.$block;
    }

    /**
     * Removes the block together with ONE adjacent separator, so the
     * surrounding blocks stay separated exactly once.
     */
    private function removeBlock(string $text, int $position, int $length): string
    {
        $separatorLength = strlen(self::BLOCK_SEPARATOR);

        if ($position > 0) {
            return substr_replace($text, '', $position - $separatorLength, $length + $separatorLength);
        }

        $hasFollowingSeparator = substr($text, $length, $separatorLength) === self::BLOCK_SEPARATOR;

        return substr_replace($text, '', 0, $length + ($hasFollowingSeparator ? $separatorLength : 0));
    }

    private function findBlock(string $text, string $block): ?int
    {
        $separatorLength = strlen(self::BLOCK_SEPARATOR);
        $offset = 0;

        while (($position = strpos($text, $block, $offset)) !== false) {
            $end = $position + strlen($block);
            $startsBlock = $position === 0 || substr($text, $position - $separatorLength, $separatorLength) === self::BLOCK_SEPARATOR;
            $endsBlock = $end === strlen($text) || substr($text, $end, $separatorLength) === self::BLOCK_SEPARATOR;

            if ($startsBlock && $endsBlock) {
                return $position;
            }

            $offset = $position + 1;
        }

        return null;
    }

    /**
     * The column has no cast, so the driver may hand back a numeric string:
     * normalize before the strict comparisons above.
     */
    private function workOrderId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function normalizedNote(?string $notes): ?string
    {
        $trimmed = trim($notes ?? '');

        return $trimmed === '' ? null : $trimmed;
    }
}
