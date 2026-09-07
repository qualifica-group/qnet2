<?php

namespace App\Support;

/**
 * Single source of truth for the "Gestore Account" position cap (spec 0080,
 * amendment A1, D2): the highest pivot `position` a `manager_slots` payload
 * (registry_user/opportunity_user) may fill, AND the highest key
 * `ProductCategory::manager_labels` may define. The two concepts were
 * duplicated as separate literal `4`s before this amendment — this class is
 * the ONE place that number lives now.
 *
 * A framework-neutral home rather than having either side derive from the
 * other: `App\Models\ProductCategory` (lower layer) must never depend on
 * `App\Http\Requests\Concerns\ValidatesManagerSlots` (a higher, Http-layer
 * trait) — that would invert the dependency direction (engineering.md §3,
 * DIP). Both instead depend downward on this neutral `App\Support` class,
 * the same role `OperationalSiteLabel`/`InputFormat` already play here.
 *
 * Raising this is a VALIDATION-LAYER-ONLY change: no DB constraint depends
 * on it (`opportunity_user`/`registry_user` are plain pivot rows with an
 * integer `position`, no schema cap).
 */
final class ManagerPositions
{
    public const int MAX = 12;

    /**
     * The "Operatore" (GA2) slot — the operative owner Gestione Richieste
     * scopes its "my rows" view on (spec 0049), now shared by the
     * Opportunita' (`Opportunity::OPERATOR_MANAGER_POSITION`, aliased to this
     * constant, spec 0087 D-2) and the Offerta (`Quote::managers()`, spec
     * 0087). One neutral home rather than each side owning its own literal
     * `2`, the same reasoning MAX already follows here.
     */
    public const int OPERATOR = 2;

    /**
     * The third G.A. slot, exposed by Gestione Richieste as its own grid
     * column (`manager_ga3`, direttiva utente 2026-09-07) beside the GA2
     * "Operatore". Unlike OPERATOR it carries no domain role of its own: it
     * scopes nothing, denormalizes onto no column and notifies nobody — it is
     * just position 3 of `quote_user`, named by the scoped category's
     * `manager_labels[3]`. It lives here for the same reason OPERATOR does:
     * the column catalogue, the row mapper, the header relabel and the writer
     * must all mean the SAME slot.
     */
    public const int GA3 = 3;

    /**
     * The subset of a `sync()` map that was genuinely ATTACHED, as
     * `userId => position` (spec 0081). A manager who merely MOVED between
     * slots comes back under sync()'s `updated` key and is deliberately left
     * out: being renumbered is not being put in charge, so it notifies
     * nobody (decisione utente 2026-08-04).
     *
     * Lives here, beside the cap, because both `registry_user` and
     * `opportunity_user` are synced from the same `manager_slots` shape by
     * two services that must not grow a second, divergent copy of this rule.
     *
     * @param  array<int, array{position: int}>  $syncMap  the map handed to sync()
     * @param  array{attached: array<int, mixed>, detached: array<int, mixed>, updated: array<int, mixed>}  $syncResult
     * @return array<int, int>
     */
    public static function attachedPositions(array $syncMap, array $syncResult): array
    {
        $positions = [];

        foreach ($syncResult['attached'] as $userId) {
            $position = $syncMap[$userId]['position'] ?? null;

            if ($position !== null) {
                $positions[(int) $userId] = $position;
            }
        }

        return $positions;
    }

    /**
     * Turn the ordered, gap-aware manager slots into the pivot sync map
     * `[userId => ['position' => n]]`: index+1 is the 1-based "G.A. n"
     * position, null slots are skipped so a removed manager leaves a
     * persistent gap. The FormRequest (ValidatesManagerSlots) guarantees no
     * duplicate user across slots.
     *
     * Lives here, beside attachedPositions() which consumes exactly this
     * structure, because three owners now sync the same `manager_slots`
     * shape (`registry_user`, `opportunity_user`, `user_work_order` — spec
     * 0096 D-4). It was already duplicated verbatim as a private method in
     * RegistryService and OpportunityService; a third copy would have made
     * the DRY violation permanent.
     *
     * @param  array<int, int|null>  $slots
     * @return array<int, array{position: int}>
     */
    public static function syncMap(array $slots): array
    {
        $map = [];

        foreach (array_values($slots) as $index => $userId) {
            if ($userId !== null) {
                $map[$userId] = ['position' => $index + 1];
            }
        }

        return $map;
    }
}
