<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Models\OperationalSite;
use App\Models\User;
use App\Services\LeadOperatorDistributor;
use App\Support\OperationalSiteLabel;

/**
 * `POST /api/assignment/selection-scope`'s `balanced_groups`/
 * `balanced_unassignable_count` (spec 0168): the operators a "Smistamento
 * equo" selection would distribute among, grouped by Sede (D-1) so the
 * dialog can let the user narrow each group before confirming.
 *
 * Grouping/pool composition are NOT reimplemented here: a group's operators
 * are the UNION, per Sede, of the very per-record pools
 * `AssignmentCandidates::byRecord()` hands to the assignment services
 * (D-1..D-4 stay the composition's, not this class's). A record with no
 * Sede or an empty pool produces no group — it is counted in
 * `unassignable_count` instead (AC-003).
 *
 * `load` is the SAME initial load `LeadOperatorDistributor`/
 * `QuoteOperatorLoads` feed the actual distribution for that domain
 * (leads/import_rows: real leads; quotes/enrollees: offers) — never a
 * separate count that could drift from what the assignment itself will
 * apply.
 */
final class BalancedPoolGroups
{
    public function __construct(
        private readonly AssignmentCandidates $candidates,
        private readonly LeadOperatorDistributor $distributor,
        private readonly QuoteOperatorLoads $quoteLoads,
    ) {}

    /**
     * @param  array<int, int>  $recordIds
     * @param  array<int, int|null>  $siteByRecord
     * @param  array<int, array<int, int>>  $categoriesByRecord
     * @return array{groups: array<int, array<string, mixed>>, unassignable_count: int}
     */
    public function build(array $recordIds, array $siteByRecord, array $categoriesByRecord, bool $loadFromQuotes): array
    {
        // Step 1: the same per-record pools the assignment itself applies.
        $candidatesByRecord = $this->candidates->byRecord($siteByRecord, $categoriesByRecord);

        // Step 2: fold the records onto a Sede => operator ids union, and a
        // Sede => record count — dropping the records nobody can take.
        [$operatorIdsBySite, $recordCountBySite, $unassignableCount] = $this->groupBySite(
            $recordIds,
            $siteByRecord,
            $candidatesByRecord,
        );

        if ($operatorIdsBySite === []) {
            return ['groups' => [], 'unassignable_count' => $unassignableCount];
        }

        // Step 3: labels, presentation and loads — one aggregate query each
        // for the whole selection, never one per Sede/operator.
        $allOperatorIds = array_values(array_unique(array_merge([], ...array_values($operatorIdsBySite))));
        $siteLabels = $this->siteLabels(array_keys($operatorIdsBySite));
        $presentationByOperator = $this->operatorPresentation($allOperatorIds);
        $loads = $loadFromQuotes
            ? $this->quoteLoads->currentLoads($allOperatorIds)
            : $this->distributor->currentLoads($allOperatorIds);

        // Step 4: assemble and order the groups.
        $groups = [];
        foreach ($operatorIdsBySite as $siteId => $operatorIds) {
            $groups[] = [
                'operational_site_id' => $siteId,
                'operational_site_label' => $siteLabels[$siteId] ?? '',
                'record_count' => $recordCountBySite[$siteId],
                'operators' => $this->orderedOperators($operatorIds, $presentationByOperator, $loads),
            ];
        }

        usort(
            $groups,
            static fn (array $a, array $b): int => [$a['operational_site_label'], $a['operational_site_id']]
                <=> [$b['operational_site_label'], $b['operational_site_id']],
        );

        return ['groups' => $groups, 'unassignable_count' => $unassignableCount];
    }

    /**
     * @param  array<int, int>  $recordIds
     * @param  array<int, int|null>  $siteByRecord
     * @param  array<int, array<int, int>>  $candidatesByRecord
     * @return array{0: array<int, array<int, int>>, 1: array<int, int>, 2: int}
     */
    private function groupBySite(array $recordIds, array $siteByRecord, array $candidatesByRecord): array
    {
        $operatorIdsBySite = [];
        $recordCountBySite = [];
        $unassignableCount = 0;

        foreach ($recordIds as $recordId) {
            $siteId = $siteByRecord[$recordId] ?? null;
            $pool = $candidatesByRecord[$recordId] ?? [];

            if ($siteId === null || $pool === []) {
                $unassignableCount++;

                continue;
            }

            $recordCountBySite[$siteId] = ($recordCountBySite[$siteId] ?? 0) + 1;
            $operatorIdsBySite[$siteId] = array_unique(array_merge($operatorIdsBySite[$siteId] ?? [], $pool));
        }

        return [$operatorIdsBySite, $recordCountBySite, $unassignableCount];
    }

    /**
     * @param  array<int, int>  $siteIds
     * @return array<int, string>
     */
    private function siteLabels(array $siteIds): array
    {
        return OperationalSite::query()
            ->whereIn('id', $siteIds)
            ->with('addresses.city')
            ->get()
            ->mapWithKeys(static fn (OperationalSite $site): array => [
                $site->id => OperationalSiteLabel::compose($site->primaryAddress),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $operatorIds
     * @return array<int, array{label: string, avatar_url: string|null}>
     */
    private function operatorPresentation(array $operatorIds): array
    {
        return User::query()
            ->whereIn('id', $operatorIds)
            ->select(['id', 'name'])
            ->with('avatar')
            ->get()
            ->mapWithKeys(static fn (User $user): array => [
                $user->id => ['label' => $user->name, 'avatar_url' => $user->avatarDataUri()],
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $operatorIds
     * @param  array<int, array{label: string, avatar_url: string|null}>  $presentationByOperator
     * @param  array<int, int>  $loads
     * @return array<int, array<string, mixed>>
     */
    private function orderedOperators(array $operatorIds, array $presentationByOperator, array $loads): array
    {
        $operators = [];

        foreach ($operatorIds as $operatorId) {
            $presentation = $presentationByOperator[$operatorId] ?? ['label' => '', 'avatar_url' => null];

            $operators[] = [
                'id' => $operatorId,
                'label' => $presentation['label'],
                'avatar_url' => $presentation['avatar_url'],
                'load' => $loads[$operatorId] ?? 0,
            ];
        }

        usort(
            $operators,
            static fn (array $a, array $b): int => [$a['label'], $a['id']] <=> [$b['label'], $b['id']],
        );

        return $operators;
    }
}
