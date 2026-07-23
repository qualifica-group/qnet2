<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `reward-types` resource (spec 0058).
 * No special overrides: every ability maps to "reward-types.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the RewardType model.
 */
class RewardTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'reward-types';
    }
}
