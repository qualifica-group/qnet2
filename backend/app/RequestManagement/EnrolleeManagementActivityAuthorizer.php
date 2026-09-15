<?php

declare(strict_types=1);

namespace App\RequestManagement;

/**
 * Read-gate of the `enrollee-management` activity resource (spec 0130,
 * D-4/D-6): the minimal subclass of RequestManagementActivityAuthorizer that
 * overrides ONLY module() — the shared authorize() logic now reads its
 * permission prefix and D-2 status filter off RequestModule::Enrollees.
 * Registered under the `enrollee-management` resource in
 * config/activity-log.php.
 */
final class EnrolleeManagementActivityAuthorizer extends RequestManagementActivityAuthorizer
{
    protected function module(): RequestModule
    {
        return RequestModule::Enrollees;
    }
}
