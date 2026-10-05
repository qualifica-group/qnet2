<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `financial-accounts` resource (spec 0189). Overrides
 * `abilities()`: accounts are never imported from a file (so `import` is
 * absent and `permissions:sync` never creates it) and the full card number is
 * revealed only through the dedicated `revealCardNumber` ability (D-1).
 */
class FinancialAccountPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'financial-accounts';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'revealCardNumber'];
    }

    public function revealCardNumber(User $user, FinancialAccount $financialAccount): bool
    {
        return $user->can($this->permission('revealCardNumber'));
    }
}
