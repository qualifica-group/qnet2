<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\WorkflowStatusGroup;
use App\Services\RequestManagement\Report\Indicators\CompaniesAddedIndicator;
use App\Services\RequestManagement\Report\Indicators\PhoneCallsIndicator;
use App\Services\RequestManagement\Report\Indicators\UnhandledCallbacksIndicator;
use App\Services\RequestManagement\Report\Indicators\UnhandledNewContactsIndicator;
use App\Services\RequestManagement\Report\Indicators\WorkflowTransitionIndicator;

/**
 * Column key -> ReportIndicator (spec 0106 data_contract): the ONLY "real"
 * (computed) indicators. The three stub columns
 * (aule_gestione/aule_partenza/presa_appuntamenti, D-5) resolve to nothing
 * here — ReportBranchRowsBuilder emits their constant 0 directly (D-15,
 * rev-2). "associati", "trattative_concluse" and "invio_presa_in_carico"
 * ("chiuso con esito positivo", user directive 2026-09-18) resolve to the
 * SAME WorkflowTransitionIndicator instance: one formula, three columns,
 * told apart only by the category they are active for.
 */
final class ReportIndicatorRegistry
{
    /**
     * @var array<string, ReportIndicator>
     */
    private readonly array $indicators;

    public function __construct(ReportBranchQuery $branchQuery, QuoteCountAggregator $aggregator)
    {
        $closedWon = new WorkflowTransitionIndicator($branchQuery, $aggregator, [WorkflowStatusGroup::ClosedWon]);

        $this->indicators = [
            'telefonate' => new PhoneCallsIndicator($branchQuery, $aggregator),
            'richiami' => new UnhandledCallbacksIndicator($branchQuery, $aggregator),
            'nuovi_contatti' => new UnhandledNewContactsIndicator($branchQuery, $aggregator),
            'potenziali' => new WorkflowTransitionIndicator($branchQuery, $aggregator, [WorkflowStatusGroup::Pending, WorkflowStatusGroup::Validated]),
            'associati' => $closedWon,
            'trattative_concluse' => $closedWon,
            'invio_presa_in_carico' => $closedWon,
            'aziende_inserite' => new CompaniesAddedIndicator($branchQuery, $aggregator),
        ];
    }

    public function resolve(string $columnKey): ?ReportIndicator
    {
        return $this->indicators[$columnKey] ?? null;
    }
}
