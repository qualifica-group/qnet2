<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * ResourceAuthorization for the `enrollee-management` resource (spec 0130):
 * the "Gestione Iscritti" work panel is the SAME field/action catalogue as
 * `request-management` (goal: "nessuna logica duplicata"), governed by its
 * OWN permission set. Extending RequestManagementAuthorization reuses every
 * field definition, ceiling rule and action mapping UNCHANGED — each
 * `$this->permission($ability)` call late-binds `resource()` to THIS class
 * ('enrollee-management'), so no method body is copied (mirrors
 * `App\Policies\EnrolleeManagementPolicy`).
 */
class EnrolleeManagementAuthorization extends RequestManagementAuthorization
{
    public function resource(): string
    {
        return 'enrollee-management';
    }
}
