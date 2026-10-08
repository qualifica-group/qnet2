<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `invoice-installments` resource (spec 0197): a read/edit view
 * over the installments of the active invoices. Installments are born and die
 * with their invoice (no create/delete) and the collection keeps its own
 * `invoices.collect` ability, so only these four abilities exist.
 */
class InvoiceInstallmentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'invoice-installments';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'update', 'export'];
    }
}
