<?php

namespace App\Services\Sectors;

use App\Models\Sector;

/**
 * The single authority on the EFFECTIVE activity of a sector (spec 0212
 * D-1/D-3): a sector is effectively inactive when its own `is_active` is
 * false OR any ancestor's is. The descendants' own flag is never rewritten;
 * the cascade is resolved here in batch (one query over id/parent_id/is_active),
 * never a query or a walk per row. Mirrors CategoryActivity.
 */
final class SectorActivity
{
    /** @var array<int, int>|null */
    private ?array $inactiveIds = null;

    /**
     * Ids of every effectively inactive sector (own-inactive plus all their
     * descendants), memoized per instance.
     *
     * @return array<int, int>
     */
    public function inactiveSectorIds(): array
    {
        return $this->inactiveIds ??= $this->resolveInactiveIds();
    }

    public function isActive(int $sectorId): bool
    {
        return ! in_array($sectorId, $this->inactiveSectorIds(), true);
    }

    /**
     * @return array<int, int>
     */
    private function resolveInactiveIds(): array
    {
        $rows = Sector::query()->get(['id', 'parent_id', 'is_active']);

        $ownInactive = [];
        $childrenByParent = [];
        foreach ($rows as $sector) {
            if (! $sector->is_active) {
                $ownInactive[] = $sector->id;
            }
            if ($sector->parent_id !== null) {
                $childrenByParent[$sector->parent_id][] = $sector->id;
            }
        }

        // Depth-first over the in-memory adjacency; `$seen` also guards a
        // corrupted cycle from looping.
        $seen = array_fill_keys($ownInactive, true);
        $stack = $ownInactive;
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (! isset($seen[$childId])) {
                    $seen[$childId] = true;
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($seen);
    }
}
