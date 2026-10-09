<?php

namespace App\Services\Sectors;

use App\Models\Sector;
use Illuminate\Support\Collection;

/**
 * Read-side resolution of the sector tree (spec 0018): the ancestor
 * chain (anti-cycle guard) and the full nested tree for the picker. Walks
 * `parent_id` in PHP rather than a raw recursive SQL query (correctness/
 * portability over cleverness — works identically on the SQLite dev/test
 * driver and MySQL production), mirroring CategoryHierarchy.
 */
final class SectorHierarchy
{
    /**
     * Defensive cap on the ancestor walk: the write-side anti-cycle guard
     * (SectorService) prevents a real cycle from ever being persisted, so
     * this only guards against corrupted data looping forever.
     */
    private const int MAX_DEPTH = 100;

    /**
     * $sector's ancestors, ROOT-FIRST (does not include $sector itself).
     *
     * @return Collection<int, Sector>
     */
    public function ancestors(Sector $sector): Collection
    {
        $chain = [];
        $currentId = $sector->parent_id;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $parent = Sector::find($currentId);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $currentId = $parent->parent_id;
            $depth++;
        }

        return collect(array_reverse($chain));
    }

    /**
     * Whether $candidateAncestorId is among $sector's ancestors — the
     * anti-cycle check: reparenting $sector under a node that descends
     * from $sector would create a cycle.
     */
    public function isAncestorOf(Sector $sector, int $candidateAncestorId): bool
    {
        return $this->ancestors($sector)->contains('id', $candidateAncestorId);
    }

    /**
     * The full sector tree, roots first, each node nesting its children.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $byParent = Sector::query()
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        return $this->buildNodes($byParent, null);
    }

    /**
     * @param  Collection<int|string, Collection<int, Sector>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function buildNodes(Collection $byParent, ?int $parentId): array
    {
        $nodes = [];

        /** @var Collection<int, Sector> $children */
        $children = $byParent->get($parentId ?? '', collect());

        foreach ($children as $sector) {
            $nodes[] = [
                'id' => $sector->id,
                'name' => $sector->name,
                'parent_id' => $sector->parent_id,
                // Spec 0212 D-5: the OWN flag; the tree stays complete.
                'is_active' => (bool) $sector->is_active,
                'children' => $this->buildNodes($byParent, $sector->id),
            ];
        }

        return $nodes;
    }
}
