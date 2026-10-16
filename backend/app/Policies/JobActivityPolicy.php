<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `job-activities` resource (spec 0271): every
 * ability maps to "job-activities.{ability}" via BasePolicy.
 */
class JobActivityPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'job-activities';
    }
}
