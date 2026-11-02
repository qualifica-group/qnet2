<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the `commission-accruals` resource (spec 0212, D-10): a read-only
 * ledger. Movements are written only by the synchronizer, so create, update
 * and delete are refused for everyone (no permission exists for them).
 */
class CommissionAccrualPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'commission-accruals';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'export'];
    }

    public function create(User $user): bool|Response
    {
        return $this->denyBecause('commission_accrual_read_only');
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $this->denyBecause('commission_accrual_read_only');
    }

    public function delete(User $user, Model $model): bool|Response
    {
        return $this->denyBecause('commission_accrual_read_only');
    }
}
