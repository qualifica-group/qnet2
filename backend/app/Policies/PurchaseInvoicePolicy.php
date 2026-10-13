<?php

namespace App\Policies;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `purchase-invoices` resource (spec 0211, D-10; spec 0250, D-9).
 * Besides the standard abilities it gates the payment of the installments
 * (`pay`) and the change of the supplier number of a registered invoice
 * (`downgrade`). The locks of a registered invoice are business rules of
 * RegisteredPurchaseInvoiceGuard, not of the policy.
 */
class PurchaseInvoicePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-invoices';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'pay', 'downgrade'];
    }

    public function pay(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can($this->permission('pay'));
    }

    /** Change or clear the supplier number of a registered invoice (spec 0250, D-2). */
    public function downgrade(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can($this->permission('downgrade'));
    }
}
