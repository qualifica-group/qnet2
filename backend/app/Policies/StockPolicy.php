<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Permission catalogue entry of the stock (equipment) module (spec 0307,
 * D-17): `viewAny` opens the module, the stock and movement reads and the
 * product and user sections; `manage` records movements and implies the
 * reading. Not bound to a model (same pattern as LedgerPolicy): the checks are
 * made by the FormRequests and by the table definitions through this class.
 */
class StockPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'stock';
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('viewAny')) || $this->manage($user);
    }

    public function manage(User $user): bool
    {
        return $user->can($this->permission('manage'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'manage'];
    }
}
