<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Dedicated policy for the `rewarded-referents` resource (spec 0059, D-6):
 * the "Referenti con Buoni" module is an aggregated, operative VIEW over
 * `referents` rows that hold at least one `Reward` — access is authorized
 * through its OWN permission set (`rewarded-referents.*`), never
 * `referents.*`, mirroring RequestManagementPolicy's precedent for a module
 * whose `modelClass()` is a shared entity but whose permission boundary must
 * stay independent.
 *
 * NOT auto-discovered by Laravel (a Referent route-model-binding resolves
 * ReferentPolicy), but still collected by `SyncPermissions`'s glob on
 * `app/Policies/*.php` — same precedent.
 *
 * Kept on the full standard 8-ability set (no `abilities()` override): D-6
 * explicitly enumerates all 8 (`viewAny, view, create, update, delete,
 * export, import, viewActivity`) even though the module is read-only
 * (scope/out — no create/update/delete row-action is ever wired), exactly
 * mirroring RequestManagementPolicy (which also keeps `create`/`delete`
 * despite having no create endpoint of its own). The unused abilities are
 * harmless: RewardedReferentsTableDefinition deliberately does NOT override
 * `authorizeUpdate()`/`authorizeDelete()`, so those two continue to resolve
 * against ReferentPolicy (`referents.update`/`referents.delete`) — a
 * `rewarded-referents.update`/`.delete` grant alone can never mutate or
 * delete a Referent row through this view (see the table definition's own
 * docblock for the full reasoning).
 */
class RewardedReferentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'rewarded-referents';
    }
}
