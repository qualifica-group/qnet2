<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

class CommissionConfigurationPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'commission-configurations';
    }
}
