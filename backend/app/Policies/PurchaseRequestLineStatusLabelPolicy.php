<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy of the `purchase-request-line-statuses` resource (spec 0248, D-2):
 * the rows are the enum cases, so there is no create/delete/import ability
 * (`permissions:sync` reads this list and never creates those permissions).
 */
class PurchaseRequestLineStatusLabelPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-request-line-statuses';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'update', 'export', 'viewActivity'];
    }
}
