<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\PurchaseRequests\PurchaseRequestVisibilityScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the `purchase-requests` resource (spec 0208). Overrides
 * `abilities()`: there is no `viewAny`/`import` permission (browsing is gated by
 * `view`), and the status capabilities (`fulfill`, `manageStatuses`) and the
 * line deletion (`deleteLine`) are dedicated abilities. Every per-record check
 * also applies the visibility scope (D-16).
 */
class PurchaseRequestPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-requests';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view', 'viewAll', 'create', 'update', 'delete', 'deleteLine', 'fulfill', 'manageStatuses', 'close', 'export', 'viewActivity'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('view'));
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission('view')) && $this->visible($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission('update')) && $this->visible($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission('delete')) && $this->visible($user, $model);
    }

    public function close(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can($this->permission('close')) && $this->visible($user, $purchaseRequest);
    }

    /** "Invia al responsabile": whoever may edit the request may re-send it. */
    public function notifyManager(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $this->update($user, $purchaseRequest);
    }

    private function visible(User $user, Model $model): bool
    {
        return $model instanceof PurchaseRequest && PurchaseRequestVisibilityScope::isVisibleTo($user, $model);
    }
}
