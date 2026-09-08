<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\Quotes\QuoteWorkflowResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The status an Opportunity displays when it has NO Quote at all (spec 0083
 * D-8, amended by the user directive 2026-09-08): the `open` row of the
 * workflow QuoteWorkflowResolver resolves from the Opportunity's OWN
 * classification, and only the GLOBAL default set's `open` row when no
 * workflow matches it.
 *
 * Why it exists: the same directive already taught the workflow resolution to
 * fall back on `opportunity_product_lines` when an Offer carries no revenue
 * line (App\Support\QuoteWorkflows\QuoteClassificationSource), which fixed
 * Gestione Richieste — every row there IS an Offer. In /opportunities the
 * quote-less row had no Offer to resolve THROUGH, so it kept displaying the
 * global default set whatever its product category said. Here the Offer that
 * does not exist is stood in for by a transient, never-persisted Quote with
 * no offer line: the classification source then reads exactly the
 * Opportunity's own product lines, and the resolver's criteria (`source_id`
 * and the custom `relation` fields, both INHERITED from the Opportunity
 * anyway) resolve identically to how they would on a real Offer of that
 * Opportunity. Same probe shape as
 * App\Http\Requests\Concerns\ValidatesQuoteWorkflowStatus' own resolution
 * quote — the rule is never re-implemented, only fed.
 *
 * Two sides share it, which is why it is a collaborator rather than more code
 * inside either: OpportunityStatusResolver (the badge) and
 * OpportunityStatusScope/OpportunityStatusColumn (the `status` set filter and
 * its value list) — the spec 0082 invariant is that a filter can never
 * disagree with the badge next to it.
 *
 * Registered `scoped` (AppServiceProvider): the QuoteWorkflowResolver it holds
 * memoizes the active workflow set AND each resolved status set per instance,
 * so a whole grid page costs those two queries once instead of once per row.
 */
final class OpportunityDefaultStatusResolver
{
    /**
     * The relations statusFor() reads through the probe. Eager-load these
     * whenever more than one Opportunity is resolved (OpportunityStatusResolver
     * re-exports them in its own EAGER_LOADS, so the grids have a single list
     * to load).
     *
     * @var array<int, string>
     */
    public const array EAGER_LOADS = ['productLines', 'customFieldValueRow'];

    /** Rows loaded per round trip by statusesForQuoteLess(). */
    private const int CHUNK_SIZE = 500;

    public function __construct(private readonly QuoteWorkflowResolver $workflows) {}

    /**
     * The row $opportunity displays while it has no Quote.
     */
    public function statusFor(Opportunity $opportunity): QuoteWorkflowStatus
    {
        $probe = $this->probe($opportunity);

        // targetStatus() on a probe carrying no status returns the resolved
        // set's `open` row and owns the "the set has no open row" abort —
        // never duplicated here.
        return $this->workflows->targetStatus($probe, $this->workflows->resolve($probe));
    }

    /**
     * The displayed status of every QUOTE-LESS row of $opportunities, keyed by
     * opportunity id — the query-side counterpart of statusFor(), for the two
     * consumers that must reason about a whole set of rows (the set filter's
     * value list and its matching).
     *
     * $opportunities is only ever used as an id SUBQUERY (its select list is
     * reset, so a caller's `withCount`/`addSelect` never travel with it): the
     * rows themselves are re-read here with just the relations the resolution
     * needs, whatever the caller's own eager loads are.
     *
     * @param  Builder<Opportunity>  $opportunities
     * @return array<int, QuoteWorkflowStatus>
     */
    public function statusesForQuoteLess(Builder $opportunities): array
    {
        $quoteLessIds = (clone $opportunities)
            ->whereDoesntHave('quotes')
            ->select('opportunities.id');

        $statuses = [];

        Opportunity::query()
            ->whereIn('opportunities.id', $quoteLessIds)
            ->with(self::EAGER_LOADS)
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $rows) use (&$statuses): void {
                foreach ($rows as $opportunity) {
                    $statuses[(int) $opportunity->id] = $this->statusFor($opportunity);
                }
            });

        return $statuses;
    }

    /**
     * The transient Offer the resolution runs on: $opportunity as its parent,
     * NO offer line. `quote_lines.product_id` is NOT NULL, so "no offer line"
     * is "no product" — the very state QuoteClassificationSource answers by
     * reading the Opportunity's product lines.
     */
    private function probe(Opportunity $opportunity): Quote
    {
        $quote = new Quote;

        $quote->setRelation('opportunity', $opportunity);
        $quote->setRelation('offerLines', new Collection);

        return $quote;
    }
}
