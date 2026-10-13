<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `purchase-order-statuses` resource (spec 0248, D-2). Overrides
 * `abilities()`: the rows are system rows, never created, deleted or imported by
 * hand, so `create`/`delete`/`import` are DELIBERATELY absent and
 * `permissions:sync` never creates them (BasePolicy::permissions() is
 * late-static-bound on this override).
 */
class PurchaseOrderStatusLabelPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'purchase-order-statuses';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'update', 'export', 'viewActivity'];
    }
}
