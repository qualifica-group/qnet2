<?php

namespace App\Services\Assignment;

use App\Services\LeadOperatorDistributor;

/**
 * The ONE composition of "who may receive this record" (spec 0113): the
 * operators of the record's own Sede (AssignmentSiteResolver) INTERSECTED
 * with those competent for the categories it demands (OperatorCompetence).
 * Shared by the three assignment surfaces so the wizard's staged rows, the
 * real leads and Gestione richieste's offers can never narrow the pool
 * differently.
 *
 * The two halves are NOT symmetric, and the asymmetry is the whole point:
 *   - the Sede filters ALWAYS. A record with no Sede has an EMPTY pool
 *     (AC-007), never a "fall back to everybody";
 *   - the competence filters only when there is something to filter on
 *     (INV-4a/D-9a): a record demanding no category keeps the whole Sede
 *     (AC-008).
 */
final class AssignmentCandidates
{
    public function __construct(
        private readonly LeadOperatorDistributor $distributor,
        private readonly OperatorCompetence $competence,
    ) {}

    /**
     * record id => candidate operator ids, ascending — the order
     * LeadOperatorDistributor::distributeAmong() relies on for its
     * lowest-id tie-break (br-balanced step 3).
     *
     * A record absent from $categoriesByRecord demands nothing, which is the
     * same as demanding an empty set.
     *
     * @param  array<int, int|null>  $siteByRecord  record id => Sede id
     * @param  array<int, array<int, int>>  $categoriesByRecord  record id => required category ids
     * @return array<int, array<int, int>>
     */
    public function byRecord(array $siteByRecord, array $categoriesByRecord): array
    {
        // Step 1: enumerate the operators of every Sede in the batch at once.
        $operatorIdsBySite = $this->distributor->operatorIdsBySite(
            array_values(array_unique(array_filter($siteByRecord, static fn (?int $siteId): bool => $siteId !== null))),
        );

        // Step 2: narrow each record's Sede pool by its own requirement.
        $candidates = [];

        foreach ($siteByRecord as $recordId => $siteId) {
            $siteOperatorIds = $siteId === null ? [] : ($operatorIdsBySite[$siteId] ?? []);

            $candidates[$recordId] = $siteOperatorIds === []
                ? []
                : $this->competence->competent($siteOperatorIds, $categoriesByRecord[$recordId] ?? []);
        }

        return $candidates;
    }
}
