<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * THE single implementation of the "solo le mie righe" rule (spec 0086,
 * scope §in "Scoping non-supervisore riscritto... in tutti e 6 i punti"): an
 * actor is in scope for a given offer when they hold
 * `request-management.viewAll` (sees every offer) OR they are that offer's
 * Supervisore (`quotes.supervisor_id`); otherwise out of scope. Bypass and
 * predicate are identical for every caller — endpoint guard, query builder,
 * or in-memory filter — the three shapes below are the SAME rule expressed
 * for each shape, so none of them can drift from one another again.
 *
 * FAIL-CLOSED (constraints, non-negotiable): a null actor never widens
 * visibility. `scopeToActor()` degrades a null/non-viewAll actor to an
 * always-empty result, never to "sees everything".
 *
 * Renamed from the pivot-position-2 rule (spec 0049) now that the offer
 * carries its own Supervisore column (D-3): `isOperatorOf` -> `isSupervisorOf`.
 */
final class RequestManagementScope
{
    /**
     * @throws HttpException 403 when $user is neither $quote's Supervisore
     *                       nor holds the viewAll ability
     */
    public function assertInScope(User $user, Quote $quote): void
    {
        if ($user->can('request-management.viewAll')) {
            return;
        }

        if ($this->isSupervisorOf($user, $quote)) {
            return;
        }

        abort(403);
    }

    /**
     * Whether $user is $quote's Supervisore (`quotes.supervisor_id`) — the
     * D-3 scoping rule, isolated so it can be asserted directly (or filtered
     * over a collection, e.g. the bulk assign/transfer in-scope checks)
     * without triggering the abort side effect.
     */
    public function isSupervisorOf(User $user, Quote $quote): bool
    {
        return $quote->supervisor_id === $user->id;
    }

    /**
     * Applies the SAME rule to a query builder rooted on (or joined to)
     * `quotes`: unrestricted for a viewAll actor, `quotes.supervisor_id =
     * $user->id` otherwise. A null $user — fail-closed — is scoped to a
     * condition that can never match a row, never left unrestricted.
     *
     * Static and stateless on purpose: TableDefinition::baseQuery() and any
     * query-rooted caller (e.g. the category tab counts once they join
     * `quotes`) can call it inline with `Auth::user()`, with no DI wiring.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToActor(Builder $query, ?User $user): Builder
    {
        if ($user?->can('request-management.viewAll')) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('quotes.id');
        }

        return $query->where('quotes.supervisor_id', $user->id);
    }
}
