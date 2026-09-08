<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report\Indicators;

use App\Enums\PersonalDataTypeEnum;
use App\Models\User;
use App\Services\RequestManagement\Report\IndicatorResult;
use App\Services\RequestManagement\Report\QuoteCountAggregator;
use App\Services\RequestManagement\Report\ReportBranchQuery;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicator;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * "Aziende inserite" (D-6, reinterpreted — the system owns no
 * `created_by`): registries of type "company", created in the range, linked
 * through the request's Opportunity. The `personal_data` join is INNER on
 * purpose (a registry with no personal_data card is neither a company nor a
 * person and stays out, AC-015-bis).
 *
 * D-10 exception: TOTAL uses its OWN `count(distinct registries.id)` and can
 * be LESS than the sum of the GA2 rows — the SAME registry can be linked to
 * requests of two different operators and is then counted once in EACH
 * operator's row but once overall (AC-015).
 */
final class CompaniesAddedIndicator implements ReportIndicator
{
    public function __construct(
        private readonly ReportBranchQuery $branchQuery,
        private readonly QuoteCountAggregator $aggregator,
    ) {}

    public function compute(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators): IndicatorResult
    {
        return new IndicatorResult(
            total: $this->aggregator->total($this->query($categoryIds, $actor, $range, $operators), 'distinct registries.id'),
            byOperator: $this->aggregator->byOperator($this->query($categoryIds, $actor, $range, $operators), 'distinct registries.id'),
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function query(array $categoryIds, ?User $actor, ReportDateRange $range, ReportOperatorFilter $operators): Builder
    {
        return $this->branchQuery->build($categoryIds, $actor, $operators)
            ->join('registries', 'registries.id', '=', 'opportunities.registry_id')
            ->join('personal_data', function (JoinClause $join): void {
                $join->on('personal_data.personable_id', '=', 'registries.id')
                    ->where('personal_data.personable_type', '=', 'registry')
                    ->where('personal_data.type', '=', PersonalDataTypeEnum::Company->value);
            })
            ->where('registries.created_at', '>=', $range->start)
            ->where('registries.created_at', '<', $range->endExclusive);
    }
}
