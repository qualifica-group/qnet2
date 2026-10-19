<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `collective-agreements` resource (spec 0272):
 * every ability maps to "collective-agreements.{ability}" via BasePolicy.
 */
class CollectiveAgreementPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'collective-agreements';
    }
}
