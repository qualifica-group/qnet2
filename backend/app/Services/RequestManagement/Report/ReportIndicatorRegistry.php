<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\WorkflowStatusGroup;
use App\Services\RequestManagement\Report\Indicators\CompaniesAddedIndicator;
use App\Services\RequestManagement\Report\Indicators\CurrentStatusGroupIndicator;
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
 *
 * Spec 0159: `unhandled_callbacks`, `unhandled_new_contacts` and
 * `current_potentials` are the range-free counterparts of `richiami`,
 * `nuovi_contatti` and `potenziali`.
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
        $potentialGroups = [WorkflowStatusGroup::Pending, WorkflowStatusGroup::Validated];

        $this->indicators = [
            'telefonate' => new PhoneCallsIndicator($branchQuery, $aggregator),
            'richiami' => new UnhandledCallbacksIndicator($branchQuery, $aggregator, withinRange: true),
            'nuovi_contatti' => new UnhandledNewContactsIndicator($branchQuery, $aggregator, withinRange: true),
            'potenziali' => new WorkflowTransitionIndicator($branchQuery, $aggregator, $potentialGroups),
            'associati' => $closedWon,
            'trattative_concluse' => $closedWon,
            'invio_presa_in_carico' => $closedWon,
            'aziende_inserite' => new CompaniesAddedIndicator($branchQuery, $aggregator),
            'unhandled_callbacks' => new UnhandledCallbacksIndicator($branchQuery, $aggregator, withinRange: false),
            'unhandled_new_contacts' => new UnhandledNewContactsIndicator($branchQuery, $aggregator, withinRange: false),
            'current_potentials' => new CurrentStatusGroupIndicator($branchQuery, $aggregator, $potentialGroups),
        ];
    }

    public function resolve(string $columnKey): ?ReportIndicator
    {
        return $this->indicators[$columnKey] ?? null;
    }
}
