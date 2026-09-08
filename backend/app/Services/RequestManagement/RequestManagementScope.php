<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * THE single implementation of the "solo le mie righe" rule (spec 0086,
 * scope §in "Scoping non-supervisore riscritto... in tutti e 6 i punti").
 * Bypass and predicate are identical for every caller — endpoint guard, query
 * builder, or in-memory filter — the three shapes below are the SAME rule
 * expressed for each shape, so none of them can drift from one another again.
 *
 * Spec 0105 turned the rule from binary into three tiers, evaluated in this
 * order:
 *  1. `request-management.viewAll` — sees every offer, nothing else evaluated.
 *  2. the actor IS that offer's GA2 "Operatore" (`quotes.operator_id`, spec
 *     0087 D-9).
 *  3. `request-management.viewSite` (D-1) — the offer's Sede operativa
 *     (`quotes.operational_site_id`) is one of the actor's own memberships,
 *     physical or remote alike (spec 0103 D-1, D-4 here).
 * Tiers 2 and 3 are a UNION, never a replacement (D-6): holding `viewSite`
 * never costs an actor the requests they operate in someone else's Sede — the
 * state spec 0079's "Trasferisci contatto" routinely produces.
 *
 * The tier-3 grant is a ROW gate, not an action grant: it widens WHICH offers
 * are in scope, never WHAT may be done to them (D-2). Every write keeps
 * asking for its own ability (`update`, `delete`, `assignOperator`,
 * `transferContact`, `assignManagerGa1`) on top.
 *
 * FAIL-CLOSED (constraints, non-negotiable): a null actor never widens
 * visibility. `scopeToActor()` degrades a null/non-viewAll actor to an
 * always-empty result, never to "sees everything". An offer with NO Sede
 * (`operational_site_id` NULL) belongs to no site and is therefore out of
 * tier 3 for everyone (D-3) — `whereIn` never matches NULL, and the record
 * shape asserts it explicitly.
 *
 * Spec 0087, D-9: the ownership column moves from `quotes.supervisor_id` to
 * `quotes.operator_id` — the Offerta's own GA2, denormalized from `quote_user`
 * (D-3). `quotes.supervisor_id` is no longer read here or anywhere else for
 * authorization (INV-5): `isSupervisorOf` -> `isOperatorOf`.
 */
final class RequestManagementScope
{
    private const string VIEW_ALL_PERMISSION = 'request-management.viewAll';

    private const string VIEW_SITE_PERMISSION = 'request-management.viewSite';

    /**
     * @throws HttpException 403 when $user is neither $quote's GA2 Operatore
     *                       nor a member of its Sede under the viewSite
     *                       grant, nor holds the viewAll ability
     */
    public function assertInScope(User $user, Quote $quote): void
    {
        if ($user->can(self::VIEW_ALL_PERMISSION)) {
            return;
        }

        if ($this->isOperatorOf($user, $quote)) {
            return;
        }

        if (self::isInActorSites($user, $quote)) {
            return;
        }

        abort(403);
    }

    /**
     * Whether $user is $quote's GA2 Operatore (`quotes.operator_id`) — the
     * D-9 scoping rule, isolated so it can be asserted directly (or filtered
     * over a collection, e.g. the bulk assign/transfer in-scope checks)
     * without triggering the abort side effect.
     */
    public function isOperatorOf(User $user, Quote $quote): bool
    {
        return $quote->operator_id === $user->id;
    }

    /**
     * The tier-3 predicate (spec 0105): $user holds `viewSite` AND $quote's
     * Sede operativa is one of their memberships. An offer with no Sede is
     * out for everyone (D-3) — asserted here rather than left to the
     * `in_array` so the intent survives a refactor of the id list.
     *
     * Static like scopeToActor() and for the same reason (D-7): the per-row
     * note affordances of QuotesTableDefinition/OpportunitiesTableDefinition
     * evaluate it with no DI wiring and no instance to hand.
     */
    public static function isInActorSites(User $user, Quote $quote): bool
    {
        if ($quote->operational_site_id === null) {
            return false;
        }

        if (! $user->can(self::VIEW_SITE_PERMISSION)) {
            return false;
        }

        return in_array($quote->operational_site_id, self::actorSiteIds($user), true);
    }

    /**
     * Every Sede operativa $user belongs to — PHYSICAL and REMOTE alike
     * (spec 0103 D-1, restated as D-4 of spec 0105): the pivot
     * `employment_profile_operational_site`, read through the relation rather
     * than through the two `primary`/`remote` accessors, which would answer
     * half the question each.
     *
     * `loadMissing('employment.operationalSites')` — the authenticated actor
     * arrives with no relations loaded and Model::preventLazyLoading() is
     * active outside production (backend.md §3); the precedent is
     * RequestCreationService::actorOperationalSiteId(). Being a loadMissing,
     * the per-row callers pay for it once per request, not once per row.
     *
     * @return array<int, int>
     */
    public static function actorSiteIds(User $user): array
    {
        return $user->loadMissing('employment.operationalSites')
            ->employment?->operationalSites->pluck('id')->all() ?? [];
    }

    /**
     * Applies the SAME rule to a query builder rooted on (or joined to)
     * `quotes`: unrestricted for a viewAll actor, otherwise the union of
     * "I am the Operatore" and "the Sede is mine" (spec 0105, D-6). A null
     * $user — fail-closed — is scoped to a condition that can never match a
     * row, never left unrestricted.
     *
     * The disjunction is wrapped in its own `where()` closure and never hung
     * off the builder's root with a bare `orWhere` (D-6): callers hand this
     * method a query that already carries their own conditions (grid filters,
     * `opportunity_id`, the category tab scope), and a root-level OR would
     * escape every one of them — widening visibility instead of narrowing it.
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
        if ($user?->can(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('quotes.id');
        }

        $siteIds = $user->can(self::VIEW_SITE_PERMISSION) ? self::actorSiteIds($user) : [];

        if ($siteIds === []) {
            return $query->where('quotes.operator_id', $user->id);
        }

        return $query->where(function (Builder $scoped) use ($user, $siteIds): void {
            $scoped->where('quotes.operator_id', $user->id)
                ->orWhereIn('quotes.operational_site_id', $siteIds);
        });
    }
}
