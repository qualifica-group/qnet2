<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\Abstracts\BasePolicy;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD policy for the `work-orders` resource (spec 0093), plus the
 * membership scoping of the user directive 2026-09-02: a Commessa is
 * readable and writable only by its own Responsabili/Partecipanti, unless
 * the actor holds `work-orders.viewAll` (or is super-admin).
 *
 * `update`/`delete` are scoped by the SAME rule as `view` on purpose: a
 * record an actor may not see is not a record they may edit or delete by
 * guessing its id.
 *
 * `viewActivity` stays resource-level (BasePolicy): the aggregated log's
 * per-record boundary is `view`, which PolicyActivityLogAuthorizer already
 * checks on the record itself.
 */
class WorkOrderPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'work-orders';
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $this->isInScope($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->isInScope($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->isInScope($user, $model);
    }

    /**
     * Resource-level gate lifting the membership scoping: with this ability
     * the actor sees every commessa, not only the ones they are Responsabile
     * or Partecipante of.
     */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission('viewAll'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewAll'];
    }

    private function isInScope(User $user, Model $model): bool
    {
        return ! $model instanceof WorkOrder || WorkOrderVisibilityScope::isVisibleTo($user, $model);
    }
}
