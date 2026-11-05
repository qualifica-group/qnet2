<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `treasury-movements` resource (spec 0360, D-2). Consultation is
 * viewAny/view (movements, statement, balances, unassigned); management is
 * create/update/delete. Movements are never imported from a file.
 */
class TreasuryMovementPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'treasury-movements';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'];
    }
}
