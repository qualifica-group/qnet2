<?php

declare(strict_types=1);

namespace App\Stats\BusinessFunctions;

use App\Models\BusinessFunction;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `business-functions` module (spec 0026): how many
 * functions exist, their unit/service split, and the headcount attached to
 * each (via the `business_function_user` pivot, the module's own relation).
 */
class BusinessFunctionsStatsDefinition extends AbstractStatsDefinition
{
    private const string PIVOT_TABLE = 'business_function_user';

    public function domain(): string
    {
        return 'business-functions';
    }

    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        return [
            $this->stat('total', $this->totalRows(), icon: 'briefcase'),
            $this->stat(
                key: 'business_units',
                value: BusinessFunction::query()->where('is_business_unit', true)->count(),
                icon: 'layers',
            ),
            $this->stat(
                key: 'business_services',
                value: BusinessFunction::query()->where('is_business_service', true)->count(),
                icon: 'target',
            ),
            $this->stat(
                key: 'with_manager',
                value: BusinessFunction::query()->whereNotNull('manager_id')->count(),
                icon: 'user-check',
            ),
            $this->distribution(
                key: 'by_users',
                items: Aggregates::topRelated(
                    query: DB::table(self::PIVOT_TABLE),
                    foreignKey: self::PIVOT_TABLE.'.business_function_id',
                    relatedTable: 'business_functions',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                // Denominator: the assignments, not the functions — a user can
                // belong to several functions, so the shares are of the total
                // membership.
                total: DB::table(self::PIVOT_TABLE)->count(),
                chart: DistributionChart::Bars,
            ),
        ];
    }
}
