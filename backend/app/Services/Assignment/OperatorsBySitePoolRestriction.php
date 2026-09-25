<?php

declare(strict_types=1);

namespace App\Services\Assignment;

/**
 * Applies the client's `operators_by_site` choice (spec 0168) to the
 * server-computed candidate pools, shared by the three balanced assignment
 * branches (LeadAssignmentService, RequestAssignmentService,
 * ImportBulkAssigner) so the restriction can never be reimplemented, and
 * therefore never drift, between them (constraints: "Restrizione del pool
 * implementata UNA volta").
 *
 * pool(record) = candidates(record) ∩ operator_ids of the entry whose
 * `operational_site_id` matches the record's OWN Sede (AssignmentSiteResolver,
 * unaffected). A record scoped to a Sede ABSENT from `operators_by_site`
 * empties to no candidates — its pool was never allowed to grow, only to
 * narrow, so "not listed" reads the same as "listed with nobody selected"
 * (rules: "Sede non elencata => pool vuoto => record skipped").
 *
 * The intersection is the WHOLE mechanism (constraints: "La lista del client
 * non puo' mai allargare il pool"): an operator submitted for a Sede but not
 * a candidate there — wrong Sede, not competent — is silently dropped,
 * never a validation error and never assigned.
 */
final class OperatorsBySitePoolRestriction
{
    /**
     * @param  array<int, array<int, int>>  $candidatesByRecord  record id => candidate operator ids
     * @param  array<int, int|null>  $siteByRecord  record id => Sede id
     * @param  array<int, array{operational_site_id: int, operator_ids: array<int, int>}>|null  $operatorsBySite  null = no restriction (retro-compat)
     * @return array<int, array<int, int>>
     */
    public function restrict(array $candidatesByRecord, array $siteByRecord, ?array $operatorsBySite): array
    {
        if ($operatorsBySite === null) {
            return $candidatesByRecord;
        }

        $operatorIdsBySite = [];
        foreach ($operatorsBySite as $entry) {
            $operatorIdsBySite[$entry['operational_site_id']] = $entry['operator_ids'];
        }

        $restricted = [];

        foreach ($candidatesByRecord as $recordId => $pool) {
            $siteId = $siteByRecord[$recordId] ?? null;
            $allowedOperatorIds = $siteId === null ? [] : ($operatorIdsBySite[$siteId] ?? []);

            $restricted[$recordId] = array_values(array_intersect($pool, $allowedOperatorIds));
        }

        return $restricted;
    }
}
