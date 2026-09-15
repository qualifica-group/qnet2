<?php

declare(strict_types=1);

namespace App\RequestManagement;

/**
 * The `enrollee-management` notable_types descriptor (spec 0130, D-4/D-6):
 * the minimal subclass of RequestManagementNotable that overrides ONLY
 * module() — every read gate, mentionable-set rule and deep link inherited
 * verbatim now reads its permission prefix and D-2 status filter off
 * RequestModule::Enrollees instead of being duplicated. Mirrors
 * EnrolleeManagementPolicy extends RequestManagementPolicy. Registered under
 * the `enrollee-management` slug in config/notes.php.
 */
final class EnrolleeManagementNotable extends RequestManagementNotable
{
    protected function module(): RequestModule
    {
        return RequestModule::Enrollees;
    }
}
