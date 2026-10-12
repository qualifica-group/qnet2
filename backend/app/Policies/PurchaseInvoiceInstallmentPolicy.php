<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `purchase-invoice-installments` resource (spec 0211, D-10): a
 * read/edit view over the installments of the payable documents. Installments
 * are born and die with their document (no create/delete) and the payment
 * keeps its own `purchase-invoices.pay` ability.
 */
class PurchaseInvoiceInstallmentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-invoice-installments';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'update', 'export'];
    }
}
