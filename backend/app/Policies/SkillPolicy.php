<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `skills` resource (spec 0286): every ability
 * maps to "skills.{ability}" via BasePolicy.
 */
class SkillPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'skills';
    }
}
