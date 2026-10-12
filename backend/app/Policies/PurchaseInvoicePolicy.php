<?php

namespace App\Policies;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `purchase-invoices` resource (spec 0211, D-10). Phase 1 only
 * needs to read the documents and to record their payments (`pay`); create,
 * update and delete arrive with the payable invoices module.
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
        return ['viewAny', 'view', 'pay'];
    }

    public function pay(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $user->can($this->permission('pay'));
    }
}
