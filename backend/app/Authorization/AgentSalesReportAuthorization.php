<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `agent-sales-report` resource (spec 0365): the
 * "Report Vendite Agenti" is a read-only table gated by `agent-sales-report.view`
 * alone. Registered only so the permission is assignable from the Role form
 * (AssignablePermissionCatalogue): no form, hence no field and no action. Which
 * offers a user sees stays up to QuoteVisibilityScope (spec 0316).
 */
class AgentSalesReportAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'agent-sales-report';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        return [];
    }
}
