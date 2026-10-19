<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `job-roles` resource (spec 0271): every
 * ability maps to "job-roles.{ability}" via BasePolicy.
 */
class JobRolePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'job-roles';
    }
}
