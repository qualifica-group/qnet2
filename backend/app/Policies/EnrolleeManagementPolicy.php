<?php

namespace App\Policies;

use App\Models\User;
use App\RequestManagement\RequestModule;

/**
 * Dedicated policy for the `enrollee-management` resource (spec 0130): the
 * "Gestione Iscritti" module is the SAME operative view over Quote records
 * as `request-management` — restricted to the `validated`/`closed_won`
 * status groups (`RequestModule::Enrollees`, `RequestManagementScope`) and
 * governed by its OWN permission set, independent of `request-management.*`.
 *
 * Extending RequestManagementPolicy reuses every ability's implementation
 * UNCHANGED (goal: "nessuna logica duplicata") — each inherited
 * `$user->can($this->permission($ability))` call late-binds `resource()` to
 * THIS class ('enrollee-management'), so no method body is copied.
 *
 * abilities() is overridden: D-4/D-8 drop `create` — Gestione Iscritti has
 * no creation surface at all (RequestModule::Enrollees), so
 * `permissions:sync` never mints `enrollee-management.create` — and spec
 * 0165 adds `viewPrimarySite`, this module's own visibility tier.
 */
class EnrolleeManagementPolicy extends RequestManagementPolicy
{
    protected function resource(): string
    {
        return 'enrollee-management';
    }

    /**
     * Spec 0165 D-2/D-3: widens the rows to the offers of the actor's
     * PHYSICAL Sede, in union with the operator and viewSite tiers. A row
     * gate only, like viewSite: every write still asks for its own ability.
     */
    public function viewPrimarySite(User $user): bool
    {
        return $user->can($this->permission(RequestModule::PRIMARY_SITE_ABILITY));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return RequestModule::Enrollees->abilities();
    }
}
