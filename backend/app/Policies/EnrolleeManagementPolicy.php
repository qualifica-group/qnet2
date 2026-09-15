<?php

namespace App\Policies;

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
 * The only override is abilities(): D-4/D-8 drop `create` — Gestione
 * Iscritti has no creation surface at all (RequestModule::Enrollees), so
 * `permissions:sync` never mints `enrollee-management.create`.
 */
class EnrolleeManagementPolicy extends RequestManagementPolicy
{
    protected function resource(): string
    {
        return 'enrollee-management';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return RequestModule::Enrollees->abilities();
    }
}
