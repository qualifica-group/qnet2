<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE single implementation of the opportunity record-visibility rule (spec
 * 0316): an actor sees an Opportunity when they hold `opportunities.viewAll`
 * (or are super-admin, through the Gate::before bypass) OR they are its
 * Supervisore (`supervisor_id`) or one of its Gestori account (`managers`,
 * any pivot position).
 *
 * The rule NARROWS, it never widens: `opportunities.view` is still required.
 *
 * Query shape and record shape are the SAME predicate expressed twice;
 * isVisibleTo() answers in memory when `managers` is loaded and otherwise
 * falls back to scopeToActor().
 *
 * FAIL-CLOSED: a null actor is scoped to a condition that never matches.
 * Static and stateless so OpportunityPolicy stays zero-argument constructible
 * (`permissions:sync` discovers policies with `new $class`).
 */
final class OpportunityVisibilityScope
{
    public const VIEW_ALL_PERMISSION = 'opportunities.viewAll';

    /**
     * @template TModel of Opportunity
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
            return $query->whereNull('opportunities.id');
        }

        return $query->where(function (Builder $scoped) use ($user): void {
            $scoped
                ->where('opportunities.supervisor_id', $user->id)
                ->orWhereHas('managers', fn (Builder $managers) => $managers->whereKey($user->id));
        });
    }

    public static function isVisibleTo(User $user, Opportunity $opportunity): bool
    {
        if ($user->can(self::VIEW_ALL_PERMISSION)) {
            return true;
        }

        if ($opportunity->supervisor_id === $user->id) {
            return true;
        }

        if ($opportunity->relationLoaded('managers')) {
            return $opportunity->managers->contains('id', $user->id);
        }

        return self::scopeToActor(Opportunity::query()->whereKey($opportunity->getKey()), $user)->exists();
    }
}
