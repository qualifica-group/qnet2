<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the "Gestione Richieste" category tab strip (spec 0064, M3;
 * migrated onto the Quote by spec 0086): the product categories that
 * actually appear in the requests visible to $user, each with the count of
 * DISTINCT offers carrying at least one product line of that category on
 * their Opportunity (D-2: an offer with N categories counts in all N,
 * AC-037).
 *
 * The scope reuses RequestManagementScope::scopeToActor() verbatim (D-3:
 * `request-management.viewAll` sees everything, otherwise only the offers
 * where the actor is `quotes.supervisor_id`) — the SAME rule every other
 * scoped query in this module applies, so this resolver can never drift from
 * them. This class lives on its own lane (spec 0064 write-surface split),
 * never touching the TableDefinition.
 */
final class RequestCategoryTabsResolver
{
    /**
     * @return Collection<int, ProductCategory>
     */
    public function resolve(User $user): Collection
    {
        // Step 1: one aggregated query — categories with >=1 offer in scope,
        // counting DISTINCT offers (a multi-category offer must not inflate
        // its own category's count).
        $query = ProductCategory::query()
            ->select(['product_categories.id', 'product_categories.name'])
            ->selectRaw('count(distinct quotes.id) as requests_count')
            ->join('opportunity_product_lines', 'opportunity_product_lines.product_category_id', '=', 'product_categories.id')
            ->join('opportunities', 'opportunities.id', '=', 'opportunity_product_lines.opportunity_id')
            ->join('quotes', 'quotes.opportunity_id', '=', 'opportunities.id');

        // Step 2: apply the D-3 supervisor scope, unless the actor holds viewAll.
        RequestManagementScope::scopeToActor($query, $user);

        // Step 3: only categories with a non-zero count in scope, ordered by name.
        return $query->groupBy('product_categories.id', 'product_categories.name')
            ->orderBy('product_categories.name')
            ->get();
    }
}
