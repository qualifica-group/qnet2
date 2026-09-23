<?php

declare(strict_types=1);

namespace App\Stats\Registries;

use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Models\Registry;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\Widget;

/**
 * Statistics panel of the `registries` module (spec 0026): the supplier share
 * of the base and the two commercial classifications (agreement, size).
 */
class RegistriesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'registries';

    public function domain(): string
    {
        return 'registries';
    }

    public function modelClass(): string
    {
        return Registry::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'briefcase'),
            $this->percentStat(
                key: 'suppliers',
                count: Registry::query()->where('is_supplier', true)->count(),
                total: $total,
                icon: 'package',
            ),
            $this->percentStat(
                key: 'qualified_suppliers',
                count: Registry::query()->where('is_qualified_supplier', true)->count(),
                total: $total,
                icon: 'check-circle',
            ),
            $this->stat(
                key: 'agreed',
                value: Registry::query()->where('agreement_status', AgreementStatusEnum::Agreed->value)->count(),
                icon: 'check-circle',
            ),
            $this->distribution(
                key: 'by_agreement_status',
                items: Aggregates::byEnumColumn(self::TABLE, 'agreement_status', AgreementStatusEnum::class),
                total: $total,
                chart: DistributionChart::Donut,
            ),
            $this->distribution(
                key: 'by_size_class',
                items: Aggregates::byEnumColumn(self::TABLE, 'size_class', SizeClassEnum::class),
                total: $total,
                chart: DistributionChart::Columns,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
                chart: TrendChart::Line,
                tone: 2,
            ),
        ];
    }
}
