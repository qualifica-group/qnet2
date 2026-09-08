<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\User;

/**
 * The GA2 Operatore an actor may filter the report by (spec 0108, D-6): the
 * distinct `quotes.operator_id` of every request in their OWN visibility
 * scope, across every branch, ALL-TIME — deliberately independent of both
 * the picked date range and the picked branches, exactly like
 * ReportCategoryAvailabilityResolver is (spec 0106 rev-2, D-12): the picker
 * populates before the actor has chosen anything, and a list that reshuffles
 * under their hands as they move the dates is worse than a stable list with
 * a few zero-valued entries in it.
 *
 * Reuses ReportBranchQuery verbatim, so it can never offer an operator the
 * report itself would not show (AC-011), and serves two callers at once: the
 * picker's own endpoint AND the allow-list both FormRequests validate
 * `operator_keys` against (D-5) — one source, so a value that passes
 * validation is by construction a value the picker could have produced.
 */
final class ReportOperatorAvailabilityResolver
{
    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly ReportBranchQuery $branchQuery,
    ) {}

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function available(?User $actor): array
    {
        // Step 1: the distinct GA2 of every in-scope request, "Non assegnato" (NULL) included.
        $operatorIds = $this->distinctOperatorIds($actor);

        // Step 2: the named operators, sorted by name as the CSV's own rows are.
        $options = User::query()
            ->whereIn('id', array_values(array_filter($operatorIds, static fn (?int $id): bool => $id !== null)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => ['key' => (string) $user->id, 'label' => $user->name])
            ->all();

        // Step 3: "Non assegnato" last, and ONLY when such a request exists —
        // the same condition under which the CSV emits that row (AC-012). Its
        // label comes from the report's own catalogue, never from a second one.
        if (in_array(null, $operatorIds, true)) {
            $options[] = [
                'key' => ReportOperatorFilter::UNASSIGNED_KEY,
                'label' => __('request-management-report.labels.unassigned'),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, int|null>
     */
    private function distinctOperatorIds(?User $actor): array
    {
        return $this->branchQuery
            ->build($this->allCategoryIds(), $actor, ReportOperatorFilter::all())
            ->distinct()
            ->pluck('quotes.operator_id')
            ->map(static fn ($id): ?int => $id === null ? null : (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function allCategoryIds(): array
    {
        return array_values(array_unique(array_merge([], ...array_map(
            static fn (ReportBranch $branch): array => $branch->categoryIds,
            $this->branches->resolve(),
        ))));
    }
}
