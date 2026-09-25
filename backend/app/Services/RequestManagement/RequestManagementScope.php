<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestModule;
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
 * Spec 0165 adds, for Gestione Iscritti only, tier 3-bis:
 *  3b. `enrollee-management.viewPrimarySite` — the offer's Sede is the actor's
 *      PHYSICAL one; remote memberships do not count (D-2).
 * Tiers 3 and 3b resolve to ONE list of Sede ids (visibleSiteIds()), so the
 * record, query and mention shapes cannot drift from each other.
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
 *
 * Spec 0130: parameterized by `RequestModule` so "Gestione Iscritti" reuses
 * every shape UNCHANGED rather than forking them — the permission prefix
 * (`$module->permission(...)`) AND the row-state filter
 * (`$module->statusGroups()`, D-2) both come off the module. Every method
 * defaults to `RequestModule::Requests`, so the refactor is at parity: no
 * existing call-site's behaviour changes by omitting the new parameter.
 * The state filter is evaluated INDEPENDENTLY of the viewAll/operator/site
 * tiers below (D-2 is orthogonal to D-5) — a viewAll Iscritti actor still
 * sees only `validated`/`closed_won` rows.
 */
final class RequestManagementScope
{
    /**
     * @throws HttpException 403 when $quote's status is out of $module's
     *                       filter, or $user is neither $quote's GA2
     *                       Operatore nor a member of its Sede under the
     *                       viewSite grant, nor holds the viewAll ability
     */
    public function assertInScope(User $user, Quote $quote, RequestModule $module = RequestModule::Requests): void
    {
        if (! self::isInStatusScope($quote, $module)) {
            abort(403);
        }

        if ($user->can($module->permission('viewAll'))) {
            return;
        }

        if ($this->isOperatorOf($user, $quote)) {
            return;
        }

        if (self::isInActorSites($user, $quote, $module)) {
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
     * The tier-3/3b predicate (spec 0105, spec 0165): $quote's Sede operativa
     * is one of visibleSiteIds(). An offer with no Sede is out for everyone
     * (D-3) — asserted here rather than left to the `in_array` so the intent
     * survives a refactor of the id list.
     *
     * Static like scopeToActor() and for the same reason (D-7): the per-row
     * note affordances of QuotesTableDefinition/OpportunitiesTableDefinition
     * evaluate it with no DI wiring and no instance to hand.
     */
    public static function isInActorSites(User $user, Quote $quote, RequestModule $module = RequestModule::Requests): bool
    {
        if ($quote->operational_site_id === null) {
            return false;
        }

        return in_array($quote->operational_site_id, self::visibleSiteIds($user, $module), true);
    }

    /**
     * The Sede ids the site tiers open to $user: every membership with
     * `viewSite` (spec 0105), else the PHYSICAL one alone with
     * `viewPrimarySite` on a module that has it (spec 0165 D-2), else none.
     * `viewSite` already covers the physical Sede, so it wins outright.
     *
     * @return array<int, int>
     */
    public static function visibleSiteIds(User $user, RequestModule $module = RequestModule::Requests): array
    {
        if ($user->can($module->permission('viewSite'))) {
            return self::actorSiteIds($user);
        }

        if (! $module->hasPrimarySiteTier() || ! $user->can($module->permission(RequestModule::PRIMARY_SITE_ABILITY))) {
            return [];
        }

        $primaryId = $user->loadMissing('employment.operationalSites')->employment?->primary_operational_site_id;

        return $primaryId === null ? [] : [$primaryId];
    }

    /**
     * The D-2 row-state predicate, isolated like isOperatorOf()/
     * isInActorSites() above so it can be asserted directly on an
     * already-loaded Quote — the in-memory counterpart of the `whereIn`
     * scopeToActor() applies to a query. `Requests` (no filter, D-2) always
     * answers true; `Enrollees` checks `quoteWorkflowStatus.group` against
     * `$module->statusGroups()`, loaded on demand (Model::preventLazyLoading()
     * is active outside production, backend.md §3).
     */
    public static function isInStatusScope(Quote $quote, RequestModule $module = RequestModule::Requests): bool
    {
        $groups = $module->statusGroups();

        if ($groups === null) {
            return true;
        }

        $group = $quote->loadMissing('quoteWorkflowStatus')->quoteWorkflowStatus?->group;

        return $group !== null && in_array($group, $groups, true);
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
     * The D-2 row-state filter (`$module->statusGroups()`) is applied FIRST
     * and unconditionally — a subquery on `quote_workflow_statuses.group`,
     * so it composes with any pre-existing join/select instead of requiring
     * one. It narrows the result independently of the viewAll/operator/site
     * tiers below: a viewAll Iscritti actor still sees only
     * `validated`/`closed_won` rows.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToActor(Builder $query, ?User $user, RequestModule $module = RequestModule::Requests): Builder
    {
        self::applyStatusFilter($query, $module);

        if ($user?->can($module->permission('viewAll'))) {
            return $query;
        }

        if ($user === null) {
            return $query->whereNull('quotes.id');
        }

        $siteIds = self::visibleSiteIds($user, $module);

        if ($siteIds === []) {
            return $query->where('quotes.operator_id', $user->id);
        }

        return $query->where(function (Builder $scoped) use ($user, $siteIds): void {
            $scoped->where('quotes.operator_id', $user->id)
                ->orWhereIn('quotes.operational_site_id', $siteIds);
        });
    }

    /**
     * The query-builder shape of isInStatusScope(): a subquery rather than a
     * join, so it never collides with a caller's own join/alias on
     * `quote_workflow_statuses` and composes with any pre-existing condition
     * on the builder (mirrors scopeToActor()'s own reasoning for its
     * disjunction closure above).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function applyStatusFilter(Builder $query, RequestModule $module): void
    {
        $groups = $module->statusGroups();

        if ($groups === null) {
            return;
        }

        $query->whereIn('quotes.quote_workflow_status_id', function ($subQuery) use ($groups): void {
            $subQuery->select('id')
                ->from('quote_workflow_statuses')
                ->whereIn('group', array_map(static fn ($group) => $group->value, $groups));
        });
    }
}
