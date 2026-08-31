<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\Opportunity;
use App\Models\Quote;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The QUERY side of "which Opportunita' does this buono ultimately come
 * from" (user directive 2026-08-31: a buono can now be born on an Offerta
 * too, not only on an Opportunita').
 *
 * Every commercial predicate the `rewarded-referents` module applies — the
 * active/completed counters, the workflow-status filter, the operator
 * filter, the opportunity filter — is expressed against the Opportunita',
 * because that is where the commercial state lives (spec 0082: computed off
 * its quotes). An Offerta-origin buono must answer those predicates through
 * its PARENT Opportunita', otherwise it silently drops out of every one of
 * them — a wrong count and an empty filter result, not an error.
 *
 * One place expresses that bridge so the counters and the filters can never
 * disagree. whereQuote() is its mirror, for the predicates expressed against
 * the OFFERTA instead (the "Offerta" advanced filter).
 */
final class RewardOriginScope
{
    /**
     * Constrain a `rewards` query to rows whose origin resolves to an
     * Opportunity matching $constraint — directly for an Opportunity origin,
     * through `quotes.opportunity_id` for an Offerta one.
     *
     * @param  Builder<Model>  $rewards
     * @param  Closure(Builder<Model>): void  $constraint
     */
    public static function whereOpportunity(Builder $rewards, Closure $constraint): void
    {
        $quoteAlias = Relation::getMorphAlias(Quote::class);

        $rewards->whereHasMorph(
            'source',
            [Opportunity::class, Quote::class],
            static function (Builder $source) use ($constraint, $quoteAlias): void {
                // The arm is picked off the QUERY's own model, not off the
                // `$type` Laravel hands the closure: that argument carries the
                // FQCN, while everything else in this codebase keys on the
                // morph ALIAS (`morph_map_is_strict`). Reading getMorphClass()
                // keeps the two from diverging.
                if ($source->getModel()->getMorphClass() === $quoteAlias) {
                    $source->whereHas('opportunity', $constraint);

                    return;
                }

                $constraint($source);
            },
        );
    }

    /**
     * The mirror of whereOpportunity(): constrain a `rewards` query to rows
     * whose origin resolves to an OFFERTA matching $constraint — directly for
     * an Offerta origin, through the opportunity's own `quotes` for an
     * Opportunita' one (at most one, ValidatesSingleQuotePerOpportunity).
     *
     * @param  Builder<Model>  $rewards
     * @param  Closure(Builder<Model>): void  $constraint
     */
    public static function whereQuote(Builder $rewards, Closure $constraint): void
    {
        $quoteAlias = Relation::getMorphAlias(Quote::class);

        $rewards->whereHasMorph(
            'source',
            [Opportunity::class, Quote::class],
            static function (Builder $source) use ($constraint, $quoteAlias): void {
                if ($source->getModel()->getMorphClass() === $quoteAlias) {
                    $constraint($source);

                    return;
                }

                $source->whereHas('quotes', $constraint);
            },
        );
    }
}
