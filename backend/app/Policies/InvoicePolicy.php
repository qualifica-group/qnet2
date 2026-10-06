<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `invoices` resource (spec 0194). Overrides `abilities()`:
 * invoices are never imported from a file (so `import` is absent and
 * `permissions:sync` never creates it) and recording a collection on an
 * installment is gated by the dedicated `collect` ability; the email history/composer by
 * `viewEmails`/`sendEmail` (spec 0195).
 */
class InvoicePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'invoices';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'collect', 'viewEmails', 'sendEmail'];
    }

    public function collect(User $user, Invoice $invoice): bool
    {
        return $user->can($this->permission('collect'));
    }

    public function viewEmails(User $user, Invoice $invoice): bool
    {
        return $user->can($this->permission('viewEmails'));
    }

    public function sendEmail(User $user, Invoice $invoice): bool
    {
        return $user->can($this->permission('sendEmail'));
    }
}
