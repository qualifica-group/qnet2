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
}
