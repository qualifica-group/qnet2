<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `staff-courses` resource (spec 0284): every
 * ability maps to "staff-courses.{ability}" via BasePolicy.
 */
class StaffCoursePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'staff-courses';
    }
}
