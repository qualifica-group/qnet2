<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `reward-statuses` resource (spec 0060).
 * No special overrides: every ability maps to "reward-statuses.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the RewardStatus model.
 */
class RewardStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'reward-statuses';
    }
}
