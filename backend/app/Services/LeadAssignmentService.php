<?php

namespace App\Services;

use App\DataObjects\Assignment\AssignmentOutcome;
use App\Enums\LeadAssignmentMode;
use App\Models\Lead;
use App\Services\Assignment\LeadCompetence;
use App\Services\Assignment\OperatorCompetence;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for POST /api/leads/assign-operators (spec 0048): bulk-
 * assign a Sede and an Operatore to many REAL leads at once. Distinct from
 * LeadService (plain per-record CRUD): this is a bulk, cross-record write,
 * kept in its own Service (SRP). Distribution for `mode=balanced` is
 * delegated to LeadOperatorDistributor — the SAME algorithm ImportService::
 * bulkAssign uses for staged import rows.
 */
class LeadAssignmentService
{
    public function __construct(
        private readonly LeadOperatorDistributor $distributor,
        private readonly LeadCompetence $leadCompetence,
        private readonly OperatorCompetence $competence,
    ) {}

    /**
     * Every lead in $leadIds always receives $operationalSiteId. In
     * `single` mode every lead also receives $operatorId; in `balanced`
     * mode operators are assigned per br-balanced (422 when the Sede has no
     * operators — see assignBalanced()). Whole operation is one transaction.
     *
     * @param  array<int, int>  $leadIds
     */
    public function assignOperators(array $leadIds, int $operationalSiteId, LeadAssignmentMode $mode, ?int $operatorId): AssignmentOutcome
    {
        return DB::transaction(function () use ($leadIds, $operationalSiteId, $mode, $operatorId): AssignmentOutcome {
            // Step 1: every targeted lead gets the chosen Sede regardless of mode.
            Lead::query()->whereIn('id', $leadIds)->update(['operational_site_id' => $operationalSiteId]);

            // Step 2: apply the operator(s) per mode.
            return $mode === LeadAssignmentMode::Single
                ? $this->assignSingleOperator($leadIds, $operatorId)
                : $this->assignBalanced($leadIds, $operationalSiteId);
        });
    }

    /**
     * `single` assigns the chosen operator to every targeted lead without
     * checking their competence (spec 0110, AC-023: user decision R-1), hence
     * nothing is ever skipped on this branch.
     *
     * @param  array<int, int>  $leadIds
     */
    private function assignSingleOperator(array $leadIds, ?int $operatorId): AssignmentOutcome
    {
        return new AssignmentOutcome(
            assigned: Lead::query()->whereIn('id', $leadIds)->update(['operator_id' => $operatorId]),
        );
    }

    /**
     * br-balanced narrowed by competence (spec 0110, AC-024): the Sede stays
     * the outer filter, but each lead is distributed only among the operators
     * competent for ITS OWN required categories (INV-1). A lead nobody is
     * competent for keeps its current operator and is counted as `skipped`;
     * it still received the Sede in step 1 of assignOperators(). The 422
     * stays reserved for a Sede with no operators at all.
     *
     * @param  array<int, int>  $leadIds
     */
    private function assignBalanced(array $leadIds, int $operationalSiteId): AssignmentOutcome
    {
        $operatorIds = $this->distributor->operatorIdsForSite($operationalSiteId);

        if ($operatorIds === []) {
            abort(422, 'The selected Sede has no operators to distribute leads to.');
        }

        $orderedLeadIds = collect($leadIds)->unique()->sort()->values()->all();
        $requiredByLead = $this->leadCompetence->requiredByLead($orderedLeadIds);

        $candidatesByLead = $this->competence->competentByRequirement(
            $operatorIds,
            array_combine($orderedLeadIds, array_map(
                static fn (int $leadId): array => $requiredByLead[$leadId] ?? [],
                $orderedLeadIds,
            )),
        );

        $assignments = $this->distributor->distributeAmong($candidatesByLead, $this->distributor->currentLoads($operatorIds));

        foreach ($this->distributor->groupByOperator($assignments) as $assignedOperatorId => $ids) {
            Lead::query()->whereIn('id', $ids)->update(['operator_id' => $assignedOperatorId]);
        }

        return new AssignmentOutcome(
            assigned: count($assignments),
            skipped: count($orderedLeadIds) - count($assignments),
        );
    }
}
