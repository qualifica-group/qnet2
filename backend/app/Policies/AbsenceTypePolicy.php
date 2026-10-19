<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `absence-types` resource (spec 0278): every
 * ability maps to "absence-types.{ability}" via BasePolicy.
 */
class AbsenceTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'absence-types';
    }
}
