<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\Assignment\ActorCompetenceReach;
use App\Services\Assignment\CompetenceReach;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * THE single implementation of the opportunity record-visibility rule (spec
 * 0316): an actor sees an Opportunity when they hold `opportunities.viewAll`
 * (or are super-admin, through the Gate::before bypass) OR they are its
 * Supervisore (`supervisor_id`) or one of its Gestori account (`managers`,
 * any pivot position).
 *
 * Spec 0340 adds a third, cumulative level: `opportunities.viewCompetence`
 * (UNION with the two above) opens the opportunities carrying at least one
 * product line in a category of the actor's assignment competence, or every
 * opportunity for an actor whose competence covers all categories. It is a ROW
 * gate, never an action grant.
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

    public const VIEW_COMPETENCE_PERMISSION = 'opportunities.viewCompetence';

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

        $reach = self::competenceReachOf($user);

        // A competence covering every category opens every opportunity (D-3).
        if ($reach->all) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user, $reach): void {
            $scoped
                ->where('opportunities.supervisor_id', $user->id)
                ->orWhereHas('managers', fn (Builder $managers) => $managers->whereKey($user->id));

            if (! $reach->isEmpty()) {
                $scoped->orWhereIn('opportunities.id', self::competenceOpportunityIds($reach));
            }
        });
    }

    /**
     * The competence tier's predicate (spec 0340, D-2): the ids of the
     * opportunities with a product line in one of $reach's categories. THE one
     * builder of it, shared with RequestManagementScope (`quotes.opportunity_id`)
     * so the two modules cannot drift. Only integer ids from CompetenceReach
     * enter the query.
     *
     * @return \Closure(QueryBuilder): void
     */
    public static function competenceOpportunityIds(CompetenceReach $reach): \Closure
    {
        return static fn (QueryBuilder $lines) => $lines
            ->select('opportunity_id')
            ->from('opportunity_product_lines')
            ->whereIn('product_category_id', $reach->categoryIds);
    }

    /**
     * The actor's reach when they hold `opportunities.viewCompetence`, none
     * otherwise.
     */
    private static function competenceReachOf(User $user): CompetenceReach
    {
        return $user->can(self::VIEW_COMPETENCE_PERMISSION)
            ? ActorCompetenceReach::for($user)
            : CompetenceReach::none();
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
            if ($opportunity->managers->contains('id', $user->id)) {
                return true;
            }

            $reach = self::competenceReachOf($user);

            if ($reach->isEmpty() || $reach->all) {
                return $reach->all;
            }

            // Spec 0340, AC-016: the grid eager-loads `productLines`, so the
            // competence verdict costs no query per row.
            if ($opportunity->relationLoaded('productLines')) {
                return $reach->reaches($opportunity->productLines->pluck('product_category_id')->all());
            }
        }

        return self::scopeToActor(Opportunity::query()->whereKey($opportunity->getKey()), $user)->exists();
    }
}
