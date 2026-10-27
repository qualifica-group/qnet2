<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single implementation of the Offerta record-visibility rule (spec 0316):
 * an actor sees a quote when they hold `{resource}.viewAll` (or are
 * super-admin, through Gate::before), OR the quote is theirs (Supervisore or
 * Gestore account, any position), OR they hold `{resource}.viewSite` and the
 * quote's operational site is one of theirs (physical or remote).
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

        // Own the disjunction in a closure so the OR cannot escape the
        // caller's filters.
        return $query->where(function (Builder $scoped) use ($user, $siteIds): void {
            $scoped
                ->where('quotes.supervisor_id', $user->id)
                ->orWhereHas('managers', fn (Builder $managers) => $managers->whereKey($user->id));

            if ($siteIds !== []) {
                $scoped->orWhereIn('quotes.operational_site_id', $siteIds);
            }
        });
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

        if (! $quote->relationLoaded('managers')) {
            return self::scopeToActor(Quote::query()->whereKey($quote->getKey()), $user, $resource)->exists();
        }

        if ($quote->supervisor_id === $user->id || $quote->managers->contains('id', $user->id)) {
            return true;
        }

        return $quote->operational_site_id !== null
            && in_array($quote->operational_site_id, self::siteIds($user, $resource), true);
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
