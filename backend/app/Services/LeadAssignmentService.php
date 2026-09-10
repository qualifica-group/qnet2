<?php

namespace App\Services;

use App\DataObjects\Assignment\AssignmentOutcome;
use App\Enums\LeadAssignmentMode;
use App\Models\Lead;
use App\Services\Assignment\AssignmentCandidates;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\LeadCompetence;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for POST /api/leads/assign-operators (spec 0048): bulk-
 * assign an Operatore to many REAL leads at once. Distinct from LeadService
 * (plain per-record CRUD): this is a bulk, cross-record write, kept in its
 * own Service (SRP). Distribution for `mode=balanced` is delegated to
 * LeadOperatorDistributor — the SAME algorithm ImportService::bulkAssign
 * uses for staged import rows.
 *
 * Since spec 0113 the Sede is no longer chosen by the caller: every lead is
 * scoped by the Sede of its OWN campaign (D-3), so one call may legitimately
 * span as many Sedi as the selection touches.
 */
class LeadAssignmentService
{
    public function __construct(
        private readonly LeadOperatorDistributor $distributor,
        private readonly LeadCompetence $leadCompetence,
        private readonly AssignmentSiteResolver $siteResolver,
        private readonly AssignmentCandidates $candidates,
    ) {}

    /**
     * Every lead in $leadIds receives the Sede of its own campaign. In
     * `single` mode every lead also receives $operatorId; in `balanced` mode
     * operators are assigned per br-balanced, narrowed to the lead's own
     * Sede and competence. Whole operation is one transaction.
     *
     * @param  array<int, int>  $leadIds
     */
    public function assignOperators(array $leadIds, LeadAssignmentMode $mode, ?int $operatorId): AssignmentOutcome
    {
        return DB::transaction(function () use ($leadIds, $mode, $operatorId): AssignmentOutcome {
            $orderedLeadIds = collect($leadIds)->unique()->sort()->values()->all();

            // Step 1: derive each lead's Sede from its campaign and persist it.
            $siteByLead = $this->resolveSites($orderedLeadIds);
            $this->writeResolvedSites($siteByLead);

            // Step 2: apply the operator(s) per mode.
            return $mode === LeadAssignmentMode::Single
                ? $this->assignSingleOperator($orderedLeadIds, $operatorId)
                : $this->assignBalanced($orderedLeadIds, $siteByLead);
        });
    }

    /**
     * lead id => Sede id for EVERY targeted lead: a lead the resolver does
     * not answer for (deleted between validation and write, no campaign, a
     * campaign with no Sede) maps to null, which downstream means "no
     * candidates" rather than "unknown".
     *
     * @param  array<int, int>  $orderedLeadIds
     * @return array<int, int|null>
     */
    private function resolveSites(array $orderedLeadIds): array
    {
        $resolved = $this->siteResolver->forLeads($orderedLeadIds);

        $siteByLead = [];

        foreach ($orderedLeadIds as $leadId) {
            $siteByLead[$leadId] = $resolved[$leadId] ?? null;
        }

        return $siteByLead;
    }

    /**
     * One mass UPDATE per DISTINCT Sede, never one per lead (spec 0113: the
     * batch may span many Sedi and must not degrade into a per-record
     * write). A lead whose Sede is null gets no write at all: the derived
     * Sede is unknown, which is not the same as "clear the one it has".
     *
     * @param  array<int, int|null>  $siteByLead
     */
    private function writeResolvedSites(array $siteByLead): void
    {
        $leadIdsBySite = [];

        foreach ($siteByLead as $leadId => $siteId) {
            if ($siteId !== null) {
                $leadIdsBySite[$siteId][] = $leadId;
            }
        }

        foreach ($leadIdsBySite as $siteId => $leadIds) {
            Lead::query()->whereIn('id', $leadIds)->update(['operational_site_id' => $siteId]);
        }
    }

    /**
     * `single` assigns the chosen operator to every targeted lead without
     * checking their competence or Sede (spec 0110, AC-023: user decision
     * R-1), hence nothing is ever skipped on this branch.
     *
     * @param  array<int, int>  $orderedLeadIds
     */
    private function assignSingleOperator(array $orderedLeadIds, ?int $operatorId): AssignmentOutcome
    {
        return new AssignmentOutcome(
            assigned: Lead::query()->whereIn('id', $orderedLeadIds)->update(['operator_id' => $operatorId]),
        );
    }

    /**
     * br-balanced scoped per record (spec 0113, AC-018): each lead is
     * distributed only among the operators of ITS OWN Sede who are competent
     * for ITS OWN required categories (AssignmentCandidates). A lead nobody
     * can take — no Sede, no operator at that Sede, or none of them
     * competent — KEEPS its current operator and is counted as `skipped`;
     * it is never overwritten with null. A batch where that holds for every
     * lead is a legitimate 200 `{0, N}`, not an error: with the Sede derived
     * per record, "this Sede has no operators" stopped being a property of
     * the whole call.
     *
     * @param  array<int, int>  $orderedLeadIds
     * @param  array<int, int|null>  $siteByLead
     */
    private function assignBalanced(array $orderedLeadIds, array $siteByLead): AssignmentOutcome
    {
        $candidatesByLead = $this->candidates->byRecord(
            $siteByLead,
            $this->leadCompetence->requiredByLead($orderedLeadIds),
        );

        // ONE load map over the union of every pool: two disjoint Sedi must
        // not rebalance in isolation (AC-011), tie-break stays lowest id.
        $assignments = $this->distributor->distributeAmong(
            $candidatesByLead,
            $this->distributor->currentLoads($this->involvedOperatorIds($candidatesByLead)),
        );

        foreach ($this->distributor->groupByOperator($assignments) as $assignedOperatorId => $ids) {
            Lead::query()->whereIn('id', $ids)->update(['operator_id' => $assignedOperatorId]);
        }

        return new AssignmentOutcome(
            assigned: count($assignments),
            skipped: count($orderedLeadIds) - count($assignments),
        );
    }

    /**
     * Every operator appearing in at least one pool, so the load map is read
     * in a single query for the whole batch.
     *
     * @param  array<int, array<int, int>>  $candidatesByLead
     * @return array<int, int>
     */
    private function involvedOperatorIds(array $candidatesByLead): array
    {
        $union = array_unique(array_merge(...array_values($candidatesByLead) ?: [[]]));

        sort($union);

        return $union;
    }
}
