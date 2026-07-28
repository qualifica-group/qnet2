<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Resolves the "Gestione Richieste" category tab strip (spec 0064, M3): the
 * product categories that actually appear in the requests visible to $user,
 * each with the count of DISTINCT requests carrying at least one product
 * line of that category (D-2: a request with N categories counts in all N).
 *
 * The scope mirrors RequestManagementTableDefinition::baseQuery() verbatim
 * (D-3: `request-management.viewAll` sees everything, otherwise only the
 * opportunities where the actor is the GA2 "Operatore" — pivot position
 * Opportunity::OPERATOR_MANAGER_POSITION on `opportunity_user`). Duplicated
 * here rather than shared because the TableDefinition builds an
 * Opportunity-rooted Builder while this resolver aggregates from
 * ProductCategory — same predicate, different query root — and this class
 * lives on its own lane (spec 0064 write-surface split), never touching the
 * TableDefinition.
 */
final class RequestCategoryTabsResolver
{
    /**
     * @return Collection<int, ProductCategory>
     */
    public function resolve(User $user): Collection
    {
        // Step 1: one aggregated query — categories with >=1 request in scope,
        // counting DISTINCT opportunities (a multi-category request must not
        // inflate its own category's count).
        $query = ProductCategory::query()
            ->select(['product_categories.id', 'product_categories.name'])
            ->selectRaw('count(distinct opportunity_product_lines.opportunity_id) as requests_count')
            ->join('opportunity_product_lines', 'opportunity_product_lines.product_category_id', '=', 'product_categories.id')
            ->join('opportunities', 'opportunities.id', '=', 'opportunity_product_lines.opportunity_id');

        // Step 2: apply the D-3 operator scope, unless the actor holds viewAll.
        $this->applyOperatorScope($query, $user);

        // Step 3: only categories with a non-zero count in scope, ordered by name.
        return $query->groupBy('product_categories.id', 'product_categories.name')
            ->orderBy('product_categories.name')
            ->get();
    }

    /**
     * @param  Builder<ProductCategory>  $query
     */
    private function applyOperatorScope(Builder $query, User $user): void
    {
        if ($user->can('request-management.viewAll')) {
            return;
        }

        $query->whereExists(function (QueryBuilder $relatedQuery) use ($user): void {
            $relatedQuery->select('opportunity_user.opportunity_id')
                ->from('opportunity_user')
                ->whereColumn('opportunity_user.opportunity_id', 'opportunities.id')
                ->where('opportunity_user.user_id', $user->id)
                ->where('opportunity_user.position', Opportunity::OPERATOR_MANAGER_POSITION);
        });
    }
}
