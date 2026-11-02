<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\PurchaseRequests\PurchaseRequestVisibilityScope;
use Illuminate\Auth\Access\Response;
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

    public function view(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('view')), fn (): bool => $this->visible($user, $model), 'purchase_request_out_of_scope');
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('update')), fn (): bool => $this->visible($user, $model), 'purchase_request_out_of_scope');
    }

    public function delete(User $user, Model $model): bool|Response
    {
        return $this->withinPerimeter($user->can($this->permission('delete')), fn (): bool => $this->visible($user, $model), 'purchase_request_out_of_scope');
    }

    public function close(User $user, PurchaseRequest $purchaseRequest): Response
    {
        return $this->withinPerimeter($user->can($this->permission('close')), fn (): bool => $this->visible($user, $purchaseRequest), 'purchase_request_out_of_scope');
    }

    /** "Invia al responsabile": whoever may edit the request may re-send it. */
    public function notifyManager(User $user, PurchaseRequest $purchaseRequest): bool|Response
    {
        return $this->update($user, $purchaseRequest);
    }

    private function visible(User $user, Model $model): bool
    {
        return $model instanceof PurchaseRequest && PurchaseRequestVisibilityScope::isVisibleTo($user, $model);
    }
}
