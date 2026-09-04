<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Collision-free full-replace sync of a POSITIONAL pivot — `registry_user`,
 * `opportunity_user`, `quote_user`, `work_order_participant`: the four tables
 * that carry a `position` under a `(owner_id, position)` UNIQUE constraint.
 *
 * Eloquent's own `BelongsToMany::sync()` cannot write these safely. It walks
 * the map row by row (attach the new ones, updateExistingPivot the rest), so
 * any payload that MOVES a member between slots duplicates a position
 * mid-flight and trips the constraint before the incumbent has been
 * renumbered — `SQLSTATE[23000] ... Duplicate entry '24-1' for key
 * 'opportunity_user_opportunity_id_position_unique'`. Two managers swapping
 * slots, or a new one taking an occupied slot while its holder moves down,
 * are ordinary edits: they must not 500.
 *
 * The fix is to FREE every position that changes hands before writing any of
 * them: rows leaving the set and rows moving are detached first, then the new
 * assignments are inserted. Lossless on these four pivots — they hold nothing
 * but the position (no timestamps, no extra columns) — and always called
 * inside the caller's transaction.
 *
 * Returns `sync()`'s own `{attached, detached, updated}` shape: the callers
 * feed it to ManagerPositions::attachedPositions(), which must keep telling a
 * genuine assignment (notifies) from a mere renumbering (does not, decisione
 * utente 2026-08-04).
 */
final class PositionalPivotSync
{
    /**
     * @param  array<int, array{position: int}>  $syncMap  as built by ManagerPositions::syncMap()
     * @return array{attached: array<int, int>, detached: array<int, int>, updated: array<int, int>}
     */
    public static function sync(BelongsToMany $relation, array $syncMap): array
    {
        // Step 1: classify the incoming map against what is persisted now.
        $current = self::currentPositions($relation);

        $detached = array_values(array_diff(array_keys($current), array_keys($syncMap)));
        $attached = array_values(array_diff(array_keys($syncMap), array_keys($current)));
        $updated = array_values(array_filter(
            array_keys($syncMap),
            static fn (int $id): bool => isset($current[$id]) && $current[$id] !== $syncMap[$id]['position'],
        ));

        // Step 2: free the positions of everyone leaving OR moving, so no two
        // rows ever claim the same slot.
        $freed = array_merge($detached, $updated);

        if ($freed !== []) {
            $relation->detach($freed, false);
        }

        // Step 3: (re)insert the members whose slot is not already correct.
        $inserts = array_intersect_key($syncMap, array_flip(array_merge($attached, $updated)));

        if ($inserts !== []) {
            $relation->attach($inserts, [], false);
        }

        if ($detached !== [] || $attached !== [] || $updated !== []) {
            $relation->touchIfTouching();
        }

        return ['attached' => $attached, 'detached' => $detached, 'updated' => $updated];
    }

    /**
     * The pivot as it stands, `relatedId => position`, read straight from the
     * pivot table: never through the (possibly stale, possibly unloaded)
     * relation on the parent model.
     *
     * @return array<int, int>
     */
    private static function currentPositions(BelongsToMany $relation): array
    {
        $relatedKey = $relation->getRelatedPivotKeyName();

        $rows = $relation->newPivotQuery()->get([$relatedKey, 'position']);

        $positions = [];

        foreach ($rows as $row) {
            $positions[(int) $row->{$relatedKey}] = (int) $row->position;
        }

        return $positions;
    }
}
