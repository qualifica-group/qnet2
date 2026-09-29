<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Support\Cache\AggregateCache;
use Illuminate\Support\Collection;

/**
 * Resolves the "Gestione Richieste" category tab strip (spec 0064, M3;
 * migrated onto the Quote by spec 0086): the product categories that
 * actually appear in the requests visible to $user, each with the count of
 * DISTINCT offers carrying at least one product line of that category on
 * their Opportunity (D-2: an offer with N categories counts in all N,
 * AC-037).
 *
 * The scope reuses RequestManagementScope::scopeToActor() verbatim — the SAME
 * rule every other scoped query in this module applies, so this resolver can
 * never drift from them. Its three tiers are documented there and nowhere
 * else, deliberately: this comment used to restate them and went stale twice
 * over (it still named `quotes.supervisor_id`, dropped as the ownership
 * column by spec 0087 D-9, and predated the `request-management.viewSite`
 * tier of spec 0105). This class lives on its own lane (spec 0064 write-surface split),
 * never touching the TableDefinition.
 */
final class RequestCategoryTabsResolver
{
    public function __construct(private readonly AggregateCache $cache) {}

    /**
     * @param  RequestModule  $module  spec 0130: governs the D-2/D-3 scope
     *                                 (row-state filter + visibility tiers).
     *                                 Defaults to `Requests`, at parity for
     *                                 every pre-0130 caller.
     * @return Collection<int, ProductCategory>
     */
    public function resolve(User $user, RequestModule $module = RequestModule::Requests): Collection
    {
        // Step 1: the per-category counts, the expensive part, cached per
        // visibility scope (spec 0178 D-5) and always exact for the writer (D-4).
        $counts = $this->cache->remember(
            'rm-tabs:'.RequestManagementScope::scopeFingerprint($user, $module),
            fn (): array => $this->countsInScope($user, $module),
            $user,
        );

        // Step 2: only categories with a non-zero count in scope, ordered by
        // name; names stay live, only the counts are cached.
        return ProductCategory::query()
            ->whereKey(array_keys($counts))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(static fn (ProductCategory $category) => $category->setAttribute('requests_count', $counts[$category->id]));
    }

    /**
     * Counts DISTINCT offers per category id (an offer with two lines on one
     * category must not inflate it), grouped on the integer id alone and joined
     * straight on `quotes.opportunity_id`: with 1M offers a GROUP BY on the name
     * plus the pass through `opportunities` doubled the cost of the whole strip.
     * The module's visibility scope is applied whatever its tiers are.
     *
     * @return array<int, int> category id => requests count
     */
    private function countsInScope(User $user, RequestModule $module): array
    {
        $query = Quote::query()
            ->join('opportunity_product_lines', 'opportunity_product_lines.opportunity_id', '=', 'quotes.opportunity_id')
            ->select('opportunity_product_lines.product_category_id')
            ->selectRaw('count(distinct quotes.id) as requests_count')
            ->groupBy('opportunity_product_lines.product_category_id');

        return RequestManagementScope::scopeToActor($query, $user, $module)
            ->pluck('requests_count', 'product_category_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }
}
