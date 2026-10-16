<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\DataObjects\ManagementControl\IncomeStatementFilters;
use Illuminate\Support\Collection;

/**
 * Splits the commesse into the segments of one dimension (spec 0268, D-22):
 * every commessa sits in exactly one segment, whose figures are the plain sum
 * of its commesse and whose by-function statement is built on those commesse
 * only. A null dimension value goes to "unattributed", always last.
 */
final class IncomeStatementSegmenter
{
    /** Work order row columns holding the id and the label of each simple dimension. */
    private const array DIMENSIONS = [
        IncomeStatementFilters::SEGMENT_COMPANY => ['company_id', 'company_name'],
        IncomeStatementFilters::SEGMENT_COMPANY_SITE => ['company_site_id', 'company_site_name'],
        IncomeStatementFilters::SEGMENT_OPERATIONAL_SITE => ['operational_site_id', 'operational_site_alias'],
        IncomeStatementFilters::SEGMENT_COMMERCIAL => ['commercial_id', 'commercial_name'],
    ];

    public function __construct(private readonly IncomeStatementGrouper $grouper) {}

    /**
     * @param  Collection<int, object>  $workOrders
     * @param  array<int, array<string, int>>  $figures  base figures by work order id
     * @param  array<int, array<int, int>>  $weights
     * @return array<int, array{key: string, label: ?string, work_orders_count: int, base: array<string, int>, functions: array<int, array<string, mixed>>}>
     */
    public function segments(Collection $workOrders, array $figures, array $weights, string $segmentBy): array
    {
        if ($segmentBy === IncomeStatementFilters::SEGMENT_NONE) {
            return [];
        }

        $segments = [];
        foreach ($workOrders->groupBy(fn (object $workOrder): string => $this->key($workOrder, $segmentBy)) as $key => $members) {
            $base = array_fill_keys(WorkOrderFigureSources::BASE_FIGURES, 0);
            foreach ($members as $workOrder) {
                foreach ($figures[$workOrder->id] as $figure => $cents) {
                    $base[$figure] += $cents;
                }
            }

            $segments[$key] = [
                'key' => (string) $key,
                'label' => $this->label($members->first(), $segmentBy, (string) $key),
                'work_orders_count' => $members->count(),
                'base' => $base,
                'functions' => $this->grouper->functions($members, $figures, $weights),
            ];
        }

        return $this->grouper->sorted($segments);
    }

    private function key(object $workOrder, string $segmentBy): string
    {
        if ($segmentBy === IncomeStatementFilters::SEGMENT_WORK_ORDER_TYPE) {
            return (string) $workOrder->type;
        }

        $value = $workOrder->{self::DIMENSIONS[$segmentBy][0]};

        return $value === null ? IncomeStatementGrouper::UNATTRIBUTED : (string) $value;
    }

    private function label(object $workOrder, string $segmentBy, string $key): ?string
    {
        if ($key === IncomeStatementGrouper::UNATTRIBUTED || $segmentBy === IncomeStatementFilters::SEGMENT_WORK_ORDER_TYPE) {
            return null;
        }

        // An operational site without alias is shown by its id, as the other grids do
        return (string) ($workOrder->{self::DIMENSIONS[$segmentBy][1]} ?? $key);
    }
}
