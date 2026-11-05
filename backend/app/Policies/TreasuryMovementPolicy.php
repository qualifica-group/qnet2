<?php

namespace App\Policies;

use App\Models\User;
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
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'reconcile'];
    }

    /** Tick/untick statement rows and save/delete bank reconciliations (spec 0363, D-4). */
    public function reconcile(User $user): bool
    {
        return $user->can($this->permission('reconcile'));
    }
}
