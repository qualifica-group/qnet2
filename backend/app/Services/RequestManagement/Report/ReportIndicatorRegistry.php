<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\ReportStatusColumn;
use App\Services\RequestManagement\Report\Indicators\AppointmentTransitionIndicator;
use App\Services\RequestManagement\Report\Indicators\CompaniesAddedIndicator;
use App\Services\RequestManagement\Report\Indicators\ConfiguredStatusIndicator;
use App\Services\RequestManagement\Report\Indicators\PhoneCallsIndicator;
use App\Services\RequestManagement\Report\Indicators\UnhandledCallbacksIndicator;

/**
 * Column key -> ReportIndicator (spec 0106 data_contract): the ONLY "real"
 * (computed) indicators. The two stub columns (aule_gestione/aule_partenza,
 * D-5) resolve to nothing here — ReportBranchRowsBuilder emits their
 * constant 0 directly (D-15, rev-2).
 *
 * Spec 0391: every status-based column is a ConfiguredStatusIndicator over
 * the statuses chosen for it in the statistics; a "(nel periodo
 * selezionato)" column reads the SAME choice as its twin without a period
 * (ReportStatusColumn::twin()) and only adds the range. Spec 0159:
 * `unhandled_callbacks` is the range-free counterpart of `richiami`.
 */
final class ReportIndicatorRegistry
{
    /**
     * @var array<string, ReportIndicator>
     */
    private readonly array $indicators;

    public function __construct(ReportBranchQuery $branchQuery, QuoteCountAggregator $aggregator, ReportColumnStatusMap $statuses)
    {
        $indicators = [
            'telefonate' => new PhoneCallsIndicator($branchQuery, $aggregator),
            'richiami' => new UnhandledCallbacksIndicator($branchQuery, $aggregator, withinRange: true),
            'aziende_inserite' => new CompaniesAddedIndicator($branchQuery, $aggregator),
            'presa_appuntamenti' => new AppointmentTransitionIndicator($branchQuery, $aggregator),
            'unhandled_callbacks' => new UnhandledCallbacksIndicator($branchQuery, $aggregator, withinRange: false),
        ];

        foreach (ReportStatusColumn::cases() as $column) {
            $twin = $column->twin();
            // A column with a twin is the range-free one; a single column follows the range.
            $indicators[$column->value] = new ConfiguredStatusIndicator($branchQuery, $aggregator, $statuses, $column, withinRange: $twin === null);

            if ($twin !== null) {
                $indicators[$twin] = new ConfiguredStatusIndicator($branchQuery, $aggregator, $statuses, $column, withinRange: true);
            }
        }

        $this->indicators = $indicators;
    }

    public function resolve(string $columnKey): ?ReportIndicator
    {
        return $this->indicators[$columnKey] ?? null;
    }
}
