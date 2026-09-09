<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\OperationalSite;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Support\Facades\DB;

/**
 * The Sedi operative an actor may filter the report by (spec 0112, D-10): the
 * Sedi of the GA2 Operatore of every request in their OWN visibility scope,
 * ALL-TIME — deliberately independent of the picked date range, of the picked
 * branches AND of the picked operators, exactly like
 * ReportOperatorAvailabilityResolver is (0108 D-6) and
 * ReportCategoryAvailabilityResolver before it (0106 rev-2, D-12): the picker
 * populates before the actor has chosen anything, and a list that reshuffles
 * under their hands is worse than a stable list with a few zero-valued
 * entries in it. Accepted consequence: an operator and a Sede that do not
 * intersect can both be selected, and the answer is zero — a correct result,
 * not a 422.
 *
 * Reuses ReportBranchQuery verbatim, so it can never offer a Sede the report
 * itself would not show, and serves two callers at once: the picker's own
 * endpoint AND the allow-list both FormRequests validate `site_keys` against
 * — one source, so a value that passes validation is by construction a value
 * the picker could have produced.
 *
 * Labels come from App\Support\OperationalSiteLabel, the ONE definition of a
 * Sede's display label (D-8) — never a second composition. Sites with no
 * address get an EMPTY label and stay in the list, first: they are real,
 * selectable Sedi with requests behind them, and hiding one for a missing
 * address would make it unfilterable.
 */
final class ReportSiteAvailabilityResolver
{
    private const string PIVOT_TABLE = 'employment_profile_operational_site';

    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly ReportBranchQuery $branchQuery,
    ) {}

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function available(?User $actor): array
    {
        // Step 1: the distinct GA2 of every in-scope request. "Non assegnato"
        // (NULL) is dropped here rather than later: it belongs to no Sede, and
        // the user excluded a "senza sede" entry from the picker (D-6).
        $operatorIds = $this->distinctOperatorIds($actor);

        // Step 2: their Sedi, the WHOLE pivot — physical and remote alike (D-5).
        $siteIds = $operatorIds === [] ? [] : $this->siteIdsOf($operatorIds);

        // Step 3: one option per Sede, labelled once, sorted by that label.
        return $this->sortedOptions($siteIds);
    }

    /**
     * @return array<int, int>
     */
    private function distinctOperatorIds(?User $actor): array
    {
        return $this->branchQuery
            ->build($this->allCategoryIds(), $actor, ReportOperatorFilter::all())
            ->whereNotNull('quotes.operator_id')
            ->distinct()
            ->pluck('quotes.operator_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $operatorIds
     * @return array<int, int>
     */
    private function siteIdsOf(array $operatorIds): array
    {
        return DB::table(self::PIVOT_TABLE.' as membership')
            ->join('employment_profiles', 'employment_profiles.id', '=', 'membership.employment_profile_id')
            ->whereIn('employment_profiles.user_id', $operatorIds)
            ->distinct()
            ->pluck('membership.operational_site_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $siteIds
     * @return array<int, array{key: string, label: string}>
     */
    private function sortedOptions(array $siteIds): array
    {
        $options = OperationalSite::query()
            ->whereIn('id', $siteIds)
            // The label reads `primaryAddress->city`, so both are eager-loaded:
            // Model::preventLazyLoading() is active outside production.
            ->with(['addresses' => fn ($addresses) => $addresses->with('city')])
            ->get()
            ->map(static fn (OperationalSite $site): array => [
                'key' => (string) $site->id,
                'label' => OperationalSiteLabel::compose($site->primaryAddress),
            ])
            ->all();

        usort($options, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        return $options;
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
