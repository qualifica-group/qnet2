<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single implementation of the "solo le mie commesse" rule (user
 * directive 2026-09-02): an actor sees a Commessa when they hold
 * `work-orders.viewAll` (or are super-admin, through the Gate::before
 * bypass) OR they are one of that commessa's Responsabili (`supervisors`)
 * or Partecipanti (`participants`) OR (spec 0316) they hold
 * `work-orders.viewSite` and one of its source offers (`quote_id`, or the
 * offers of its pivot lines) sits in one of the actor's operational sites.
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

        return $query->where(function (Builder $scoped) use ($user, $siteIds): void {
            $scoped
                ->whereHas('supervisors', fn (Builder $supervisors) => $supervisors->whereKey($user->id))
                ->orWhereHas('participants', fn (Builder $participants) => $participants->whereKey($user->id));

            if ($siteIds !== []) {
                $scoped->orWhere(fn (Builder $bySite) => $bySite->withSourceQuoteInSites($siteIds));
            }
        });
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

            $siteIds = self::siteIds($user);

            if ($siteIds === []) {
                return false;
            }

            $inMemory = self::isInSitesInMemory($workOrder, $siteIds);

            if ($inMemory !== null) {
                return $inMemory;
            }
        }

        return self::scopeToActor(WorkOrder::query()->whereKey($workOrder->getKey()), $user)->exists();
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
