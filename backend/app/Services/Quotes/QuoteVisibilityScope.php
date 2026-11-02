<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\ActorCompetenceReach;
use App\Services\Assignment\CompetenceReach;
use App\Services\RequestManagement\RequestManagementScope;
use App\Tables\CategoryTabs\RevenueCategoryQuoteIds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * THE single implementation of the Offerta record-visibility rule (spec 0316):
 * an actor sees a quote when they hold `{resource}.viewAll` (or are
 * super-admin, through Gate::before), OR the quote is theirs (Supervisore or
 * Gestore account, any position), OR they hold `{resource}.viewSite` and the
 * quote's operational site is one of theirs (physical or remote), OR (spec
 * 0340) they hold `{resource}.viewCompetence` and the quote carries a REVENUE
 * line on a category their assignment competence reaches (all quotes when the
 * competence is the "all categories" wildcard).
 *
 * Parameterized on the permission prefix so Contratti reuse the very same
 * predicate (through `whereHas('quote', ...)`) with their own permissions.
 *
 * The rule NARROWS, it never widens: the resource's `view` permission is
 * still required by the policy. FAIL-CLOSED: a null actor matches no row.
 * Static and stateless like WorkOrderVisibilityScope.
 */
final class QuoteVisibilityScope
{
    public const DEFAULT_RESOURCE = 'quotes';

    public const VIEW_SITE_ABILITY = 'viewSite';

    public const VIEW_ALL_ABILITY = 'viewAll';

    public const VIEW_COMPETENCE_ABILITY = 'viewCompetence';

    /** Row attribute projected by withCompetenceVerdict(): the competence tier's verdict for the row. */
    public const COMPETENCE_VERDICT_ATTRIBUTE = 'competence_scoped';

    /**
     * @template TModel of Quote
     *
     * @param  Builder<TModel>  $query  rooted on (or nested under) `quotes`
     * @return Builder<TModel>
     */
    public static function scopeToActor(Builder $query, ?User $user, string $resource = self::DEFAULT_RESOURCE): Builder
    {
        if ($user === null) {
            return $query->whereNull('quotes.id');
        }

        if ($user->can($resource.'.'.self::VIEW_ALL_ABILITY)) {
            return $query;
        }

        $siteIds = self::siteIds($user, $resource);
        $reach = self::competenceReach($user, $resource);

        // Own the disjunction in a closure so the OR cannot escape the
        // caller's filters.
        return $query->where(function (Builder $scoped) use ($user, $siteIds, $reach): void {
            $scoped
                ->where('quotes.supervisor_id', $user->id)
                ->orWhereHas('managers', fn (Builder $managers) => $managers->whereKey($user->id));

            if ($siteIds !== []) {
                $scoped->orWhereIn('quotes.operational_site_id', $siteIds);
            }

            if ($reach !== null) {
                $reach->all
                    ? $scoped->orWhereNotNull('quotes.id')
                    : $scoped->orWhereIn('quotes.id', RevenueCategoryQuoteIds::subquery($reach->categoryIds));
            }
        });
    }

    /**
     * Projects the competence tier's verdict on each row (spec 0340, AC-016),
     * so isVisibleTo() answers a competence-only row from the attribute instead
     * of one query per row. Built from the SAME subquery as scopeToActor().
     * No-op when the actor has no competence tier or it covers everything.
     *
     * @template TModel of Quote
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withCompetenceVerdict(Builder $query, ?User $user, string $resource = self::DEFAULT_RESOURCE): Builder
    {
        $reach = $user === null ? null : self::competenceReach($user, $resource);

        if ($reach === null || $reach->all) {
            return $query;
        }

        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->getTable().'.*');
        }

        return $query->addSelect([
            self::COMPETENCE_VERDICT_ATTRIBUTE => DB::table('quotes as competence_probe')
                ->select('competence_probe.id')
                ->whereColumn('competence_probe.id', 'quotes.id')
                ->whereIn('competence_probe.id', RevenueCategoryQuoteIds::subquery($reach->categoryIds))
                ->limit(1),
        ]);
    }

    /**
     * The same rule for one record, answered in memory when `managers` is
     * loaded so the table's per-row Gate checks stay query-free.
     */
    public static function isVisibleTo(User $user, Quote $quote, string $resource = self::DEFAULT_RESOURCE): bool
    {
        if ($user->can($resource.'.'.self::VIEW_ALL_ABILITY)) {
            return true;
        }

        if ($quote->relationLoaded('managers')) {
            $inMemory = self::tiersInMemory($user, $quote, $resource);

            if ($inMemory !== null) {
                return $inMemory;
            }
        }

        return self::scopeToActor(Quote::query()->whereKey($quote->getKey()), $user, $resource)->exists();
    }

    /**
     * Own / site / competence verdict from loaded data only; null when the
     * competence tier applies but the row carries no projected verdict.
     */
    private static function tiersInMemory(User $user, Quote $quote, string $resource): ?bool
    {
        if ($quote->supervisor_id === $user->id || $quote->managers->contains('id', $user->id)) {
            return true;
        }

        if ($quote->operational_site_id !== null
            && in_array($quote->operational_site_id, self::siteIds($user, $resource), true)) {
            return true;
        }

        $reach = self::competenceReach($user, $resource);

        if ($reach === null) {
            return false;
        }

        if ($reach->all) {
            return true;
        }

        return array_key_exists(self::COMPETENCE_VERDICT_ATTRIBUTE, $quote->getAttributes())
            ? $quote->getAttribute(self::COMPETENCE_VERDICT_ATTRIBUTE) !== null
            : null;
    }

    /**
     * The actor's non-empty competence reach, or null when they lack
     * `{resource}.viewCompetence` or have no competence (the tier adds nothing).
     */
    private static function competenceReach(User $user, string $resource): ?CompetenceReach
    {
        if (! $user->can($resource.'.'.self::VIEW_COMPETENCE_ABILITY)) {
            return null;
        }

        $reach = ActorCompetenceReach::for($user);

        return $reach->isEmpty() ? null : $reach;
    }

    /**
     * @return array<int, int>
     */
    private static function siteIds(User $user, string $resource): array
    {
        return $user->can($resource.'.'.self::VIEW_SITE_ABILITY)
            ? RequestManagementScope::actorSiteIds($user)
            : [];
    }
}
