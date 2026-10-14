<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the `purchase-orders` resource (spec 0210, D-20). Pure permission
 * checks: whoever has `view` sees every order (no per-user scope), and the
 * status rule of D-10 is a 409 raised by the services, not a 403. There is no
 * `viewAny`/`import` permission (browsing is gated by `view`); `changeStatus`,
 * `approve`, `merge`, `send` and `receive` (deliveries and residual closing,
 * spec 0254, D-6) are dedicated abilities.
 */
class PurchaseOrderPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-orders';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view', 'create', 'update', 'delete', 'changeStatus', 'approve', 'merge', 'send', 'receive', 'export', 'viewActivity'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('view'));
    }

    public function changeStatus(User $user, Model $model): bool
    {
        return $user->can($this->permission('changeStatus'));
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can($this->permission('approve'));
    }

    public function merge(User $user, Model $model): bool
    {
        return $user->can($this->permission('merge'));
    }

    public function send(User $user, Model $model): bool
    {
        return $user->can($this->permission('send'));
    }

    public function receive(User $user, Model $model): bool
    {
        return $user->can($this->permission('receive'));
    }
}
