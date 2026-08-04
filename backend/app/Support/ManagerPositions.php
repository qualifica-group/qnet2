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
}
