<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Assignment\ActorCompetenceReach;
use App\Services\Assignment\CompetenceReach;
use App\Services\RequestManagement\RequestManagementScope;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * THE single implementation of the "solo le mie commesse" rule (user
 * directive 2026-09-02): an actor sees a Commessa when they hold
 * `work-orders.viewAll` (or are super-admin, through the Gate::before
 * bypass) OR they are one of that commessa's Responsabili (`supervisors`)
 * or Partecipanti (`participants`) OR (spec 0316) they hold
 * `work-orders.viewSite` and one of its source offers (`quote_id`, or the
 * offers of its pivot lines) sits in one of the actor's operational sites OR
 * (spec 0340) they hold `work-orders.viewCompetence` and a source offer carries
 * a REVENUE line on a category their assignment competence reaches (every
 * commessa when the competence is the "all categories" wildcard).
 *
 * The rule NARROWS, it never widens: being a Responsabile does not grant
 * `work-orders.view` — the resource permission is still required, exactly as
 * `request-management.viewAll` narrows RequestManagementScope.
 *
 * Query shape and record shape are the SAME predicate expressed twice, so
 * the table's rows and the policy's per-record verdict can never disagree:
 * isVisibleTo() falls back to scopeToActor() whenever it cannot answer from
 * already-loaded relations.
 *
 * FAIL-CLOSED (non-negotiable): a null actor is scoped to a condition that
 * can never match a row, never left unrestricted.
 *
 * Static and stateless like RequestManagementScope::scopeToActor(), for the
 * same two reasons: TableDefinition::baseQuery() calls it inline with
 * `Auth::user()` and no DI wiring, and WorkOrderPolicy must stay
 * zero-argument constructible — `permissions:sync` discovers policies with
 * `new $class`.
 */
final class WorkOrderVisibilityScope
{
    public const VIEW_ALL_PERMISSION = 'work-orders.viewAll';

    public const VIEW_SITE_PERMISSION = 'work-orders.viewSite';

    public const VIEW_COMPETENCE_PERMISSION = 'work-orders.viewCompetence';

    /** Row attribute projected by withCompetenceVerdict(): the competence tier's verdict for the row. */
    public const COMPETENCE_VERDICT_ATTRIBUTE = 'competence_scoped';

    /**
     * @template TModel of WorkOrder
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
            return $query->whereNull('work_orders.id');
        }

        $siteIds = self::siteIds($user);
        $reach = self::competenceReach($user);

        return $query->where(function (Builder $scoped) use ($user, $siteIds, $reach): void {
            $scoped
                ->whereHas('supervisors', fn (Builder $supervisors) => $supervisors->whereKey($user->id))
                ->orWhereHas('participants', fn (Builder $participants) => $participants->whereKey($user->id));

            if ($siteIds !== []) {
                $scoped->orWhere(fn (Builder $bySite) => $bySite->withSourceQuoteInSites($siteIds));
            }

            if ($reach !== null) {
                $reach->all
                    ? $scoped->orWhereNotNull('work_orders.id')
                    : $scoped->orWhereIn('work_orders.id', WorkOrderSourceQuotes::withRevenueCategory($reach->categoryIds));
            }
        });
    }

    /**
     * Whether scopeToActor() leaves the actor every commessa: `viewAll`, or the
     * "all categories" competence. Lets a caller skip the subquery (spec 0379 D-7).
     */
    public static function seesEveryWorkOrder(User $user): bool
    {
        return $user->can(self::VIEW_ALL_PERMISSION) || self::competenceReach($user)?->all === true;
    }

    /**
     * Projects the competence tier's verdict on each row (spec 0340, AC-016),
     * so isVisibleTo() answers a competence-only row from the attribute instead
     * of one query per row. Built from the SAME subquery as scopeToActor().
     * No-op when the actor has no competence tier or it covers everything, and
     * for `work-orders.viewAll`: isVisibleTo() answers before reading the verdict,
     * and this subquery is the most expensive part of the page.
     *
     * @template TModel of WorkOrder
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withCompetenceVerdict(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->can(self::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        $reach = self::competenceReach($user);

        if ($reach === null || $reach->all) {
            return $query;
        }

        if ($query->getQuery()->columns === null) {
            $query->select('work_orders.*');
        }

        return $query->addSelect([
            self::COMPETENCE_VERDICT_ATTRIBUTE => DB::table('work_orders as competence_probe')
                ->select('competence_probe.id')
                ->whereColumn('competence_probe.id', 'work_orders.id')
                ->whereIn('competence_probe.id', WorkOrderSourceQuotes::withRevenueCategory($reach->categoryIds))
                ->limit(1),
        ]);
    }

    /**
     * The same rule for one record. The in-memory branch is what keeps the
     * table affordable: WorkOrdersTableDefinition asks the Gate three times
     * per row, and both membership relations are eager-loaded there, so no
     * branch of that loop hits the database.
     */
    public static function isVisibleTo(User $user, WorkOrder $workOrder): bool
    {
        if ($user->can(self::VIEW_ALL_PERMISSION)) {
            return true;
        }

        if ($workOrder->relationLoaded('supervisors') && $workOrder->relationLoaded('participants')) {
            if ($workOrder->supervisors->contains('id', $user->id) || $workOrder->participants->contains('id', $user->id)) {
                return true;
            }

            $inMemory = self::tiersInMemory($user, $workOrder);

            if ($inMemory !== null) {
                return $inMemory;
            }
        }

        return self::scopeToActor(WorkOrder::query()->whereKey($workOrder->getKey()), $user)->exists();
    }

    /**
     * Site / competence verdict from loaded data only; null when a needed
     * relation or projected verdict is missing, so the caller falls back to
     * the query shape.
     */
    private static function tiersInMemory(User $user, WorkOrder $workOrder): ?bool
    {
        $siteIds = self::siteIds($user);

        if ($siteIds !== []) {
            $bySite = self::isInSitesInMemory($workOrder, $siteIds);

            if ($bySite === null) {
                return null;
            }

            if ($bySite) {
                return true;
            }
        }

        $reach = self::competenceReach($user);

        if ($reach === null) {
            return false;
        }

        if ($reach->all) {
            return true;
        }

        return array_key_exists(self::COMPETENCE_VERDICT_ATTRIBUTE, $workOrder->getAttributes())
            ? $workOrder->getAttribute(self::COMPETENCE_VERDICT_ATTRIBUTE) !== null
            : null;
    }

    /**
     * The actor's non-empty competence reach, or null when they lack
     * `work-orders.viewCompetence` or have no competence (the tier adds nothing).
     */
    private static function competenceReach(User $user): ?CompetenceReach
    {
        if (! $user->can(self::VIEW_COMPETENCE_PERMISSION)) {
            return null;
        }

        $reach = ActorCompetenceReach::for($user);

        return $reach->isEmpty() ? null : $reach;
    }

    /**
     * The actor's sites for the site tier: empty (no site branch at all) unless
     * they hold `work-orders.viewSite`.
     *
     * @return array<int, int>
     */
    private static function siteIds(User $user): array
    {
        return $user->can(self::VIEW_SITE_PERMISSION) ? RequestManagementScope::actorSiteIds($user) : [];
    }

    /**
     * Site verdict from loaded relations only: the offer when `quote_id` is set,
     * the pivot lines' offers otherwise (what the table's baseQuery loads).
     * Null when a needed relation is not loaded, so the caller falls back to
     * the query shape.
     *
     * @param  array<int, int>  $siteIds
     */
    private static function isInSitesInMemory(WorkOrder $workOrder, array $siteIds): ?bool
    {
        if ($workOrder->quote_id !== null) {
            return $workOrder->relationLoaded('quote')
                ? in_array($workOrder->quote?->operational_site_id, $siteIds, true)
                : null;
        }

        if (! $workOrder->relationLoaded('quoteLines')
            || $workOrder->quoteLines->contains(fn ($line) => ! $line->relationLoaded('quote'))) {
            return null;
        }

        return $workOrder->quoteLines->contains(
            fn ($line) => in_array($line->quote?->operational_site_id, $siteIds, true)
        );
    }
}
