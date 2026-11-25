<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;
use App\Services\WorkOrders\WorkOrderTypeAccess;

/**
 * The viewing permissions of the "Commesse di Progetto" module (user decision
 * 2026-11-25): `viewAny` lists the project commesse, `view` opens one. Bound to
 * no model: the record is a WorkOrder, whose WorkOrderPolicy asks for these
 * on a project commessa (WorkOrderTypeAccess). Declared here so
 * `permissions:sync` creates both.
 */
class ProjectWorkOrderPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return WorkOrderTypeAccess::PROJECT_RESOURCE;
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view'];
    }
}
