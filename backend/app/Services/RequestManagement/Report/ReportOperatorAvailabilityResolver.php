<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\User;
use App\RequestManagement\RequestModule;
use Illuminate\Support\Facades\DB;

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
 *
 * Spec 0130: $module (defaulting to RequestModule::Requests) flows into
 * ReportBranchQuery::build() below, so the GA2 list an Iscritti actor sees
 * is narrowed to the module's own D-2 row-state filter too.
 */
final class ReportOperatorAvailabilityResolver
{
    private const string PIVOT_TABLE = 'employment_profile_operational_site';

    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly ReportBranchQuery $branchQuery,
    ) {}

    /**
     * Each option carries `site_keys`, the Sedi of that GA2 (the WHOLE pivot,
     * physical and remote alike, as ReportSiteFilter reads it): the picker
     * narrows the operator list to the Sedi already chosen with it (user
     * directive 2026-09-18). Validation only reads `key`.
     *
     * @return array<int, array{key: string, label: string, site_keys: array<int, string>}>
     */
    public function available(?User $actor, RequestModule $module = RequestModule::Requests): array
    {
        // Step 1: the distinct GA2 of every in-scope request, "Non assegnato" (NULL) included.
        $operatorIds = $this->distinctOperatorIds($actor, $module);
        $namedIds = array_values(array_filter($operatorIds, static fn (?int $id): bool => $id !== null));

        // Step 2: the Sedi of each named operator, in one query.
        $siteKeys = $namedIds === [] ? [] : $this->siteKeysByOperator($namedIds);

        // Step 3: the named operators, sorted by name as the CSV's own rows are.
        $options = User::query()
            ->whereIn('id', $namedIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'key' => (string) $user->id,
                'label' => $user->name,
                'site_keys' => $siteKeys[$user->id] ?? [],
            ])
            ->all();

        // Step 4: "Non assegnato" last, and ONLY when such a request exists —
        // the same condition under which the CSV emits that row (AC-012). Its
        // label comes from the report's own catalogue, never from a second one.
        if (in_array(null, $operatorIds, true)) {
            $options[] = [
                'key' => ReportOperatorFilter::UNASSIGNED_KEY,
                'label' => __('request-management-report.labels.unassigned'),
                // It belongs to no Sede (spec 0112 D-6).
                'site_keys' => [],
            ];
        }

        return $options;
    }

    /**
     * @return array<int, int|null>
     */
    private function distinctOperatorIds(?User $actor, RequestModule $module): array
    {
        return $this->branchQuery
            ->build($this->allCategoryIds(), $actor, ReportOperatorFilter::all(), module: $module)
            ->distinct()
            ->pluck('quotes.operator_id')
            ->map(static fn ($id): ?int => $id === null ? null : (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $operatorIds
     * @return array<int, array<int, string>>
     */
    private function siteKeysByOperator(array $operatorIds): array
    {
        $siteKeys = [];

        DB::table(self::PIVOT_TABLE.' as membership')
            ->join('employment_profiles', 'employment_profiles.id', '=', 'membership.employment_profile_id')
            ->whereIn('employment_profiles.user_id', $operatorIds)
            ->distinct()
            ->orderBy('membership.operational_site_id')
            ->get(['employment_profiles.user_id', 'membership.operational_site_id'])
            ->each(static function (object $row) use (&$siteKeys): void {
                $siteKeys[(int) $row->user_id][] = (string) $row->operational_site_id;
            });

        return $siteKeys;
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
