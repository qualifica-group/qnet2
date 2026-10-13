<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the `commission-settlements` resource (spec 0213, D-8): create =
 * launch a run, update = recalculate / edit notes, delete = remove a draft.
 * Permissions only: the state rules (draft/issued/cancelled) are the Services'
 * and answer 409, never 403. The runs are not a resource of their own and are
 * read with `commission-settlements.viewAny`.
 */
class CommissionSettlementPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'commission-settlements';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'issue', 'cancel', 'adjust', 'export', 'viewActivity'];
    }

    public function issue(User $user, Model $model): bool
    {
        return $user->can($this->permission('issue'));
    }

    public function cancel(User $user, Model $model): bool
    {
        return $user->can($this->permission('cancel'));
    }

    /**
     * Manual adjustments are created without a settlement (standalone), so
     * the ability carries no model.
     */
    public function adjust(User $user): bool
    {
        return $user->can($this->permission('adjust'));
    }
}
