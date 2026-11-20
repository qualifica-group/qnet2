<?php

namespace App\Services\Merge;

/**
 * Duplicate detection over collection elements (spec 0394, D-6): an element is
 * a duplicate of the first earlier element with the same non-null match key.
 */
final class MergeItemDeduper
{
    /**
     * @param  list<MergeItem>  $items  in priority order
     * @return array<int, int> index in $items => id of the element it duplicates
     */
    public static function duplicates(array $items): array
    {
        $firstSeen = [];
        $duplicates = [];

        foreach ($items as $index => $item) {
            if ($item->matchKey === null) {
                continue;
            }

            if (isset($firstSeen[$item->matchKey])) {
                $duplicates[$index] = $firstSeen[$item->matchKey];

                continue;
            }

            $firstSeen[$item->matchKey] = $item->id;
        }

        return $duplicates;
    }

    /**
     * The elements of the duplicates to move: survivor elements first (they
     * always stay and win a tie), then those neither discarded nor duplicated.
     *
     * @param  list<MergeItem>  $items
     * @param  list<int>  $discardedIds
     * @return list<MergeItem>
     */
    public static function toMove(array $items, int $survivorId, array $discardedIds): array
    {
        $kept = array_values(array_filter(
            $items,
            static fn (MergeItem $item): bool => $item->recordId === $survivorId || ! in_array($item->id, $discardedIds, true),
        ));
        usort($kept, static fn (MergeItem $a, MergeItem $b): int => ($b->recordId === $survivorId) <=> ($a->recordId === $survivorId));
        $duplicates = self::duplicates($kept);

        $moving = [];

        foreach ($kept as $index => $item) {
            if ($item->recordId !== $survivorId && ! isset($duplicates[$index])) {
                $moving[] = $item;
            }
        }

        return $moving;
    }
}
