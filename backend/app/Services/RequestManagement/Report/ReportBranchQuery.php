<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The BASE query every indicator of spec 0106 starts from: `quotes` joined
 * to its `opportunities` row, restricted to the branch's category ids via a
 * correlated `whereExists` on `opportunity_product_lines` — never a `join`,
 * which would fan a quote out once per matching product line and over-count
 * anything joined on top of it (e.g. "N. Telefonate Effettuate"'s
 * `count(notes.id)`, AC-016's "one product line match, one count"), then the
 * module's OWN visibility rule (RequestManagementScope::scopeToActor,
 * security.md — mandatory on every query of this report, no exceptions; the
 * disjunction it applies is already wrapped in its own closure there),
 * and finally the selected GA2 Operatore (spec 0108 D-1) — the ONE place the
 * operator filter enters the report, which is why no indicator had to learn
 * anything about it. Applied LAST, after the scope, so it can only ever
 * narrow what the actor may already see.
 */
final class ReportBranchQuery
{
    /**
     * @param  array<int, int>  $categoryIds
     */
    public function build(array $categoryIds, ?User $actor, ReportOperatorFilter $operators): Builder
    {
        $query = Quote::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->whereExists(function (QueryBuilder $sub) use ($categoryIds): void {
                $sub->selectRaw('1')
                    ->from('opportunity_product_lines')
                    ->whereColumn('opportunity_product_lines.opportunity_id', 'opportunities.id')
                    ->whereIn('opportunity_product_lines.product_category_id', $categoryIds);
            });

        return $operators->applyTo(RequestManagementScope::scopeToActor($query, $actor));
    }
}
