<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Permission catalogue entry of "Statistiche Gestione Richieste" (spec 0185,
 * spec 0391 D-5): `request-statistics.view` opens the page and its endpoints,
 * `request-statistics.update` saves which statuses the columns count. Not
 * bound to a model, so abilities() is reduced to these two (same pattern as
 * ManagementControlPolicy). The checks are in the controllers through
 * RequestModule::STATISTICS_PERMISSION / STATISTICS_CONFIGURE_PERMISSION.
 */
class RequestStatisticsPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'request-statistics';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view', 'update'];
    }
}
