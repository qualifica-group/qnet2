<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\User;
use App\Products\ProductCategoryBranches;
use App\Services\Assignment\ActorCompetenceReach;

/**
 * The parent categories ("Categoria madre") an actor works on in a list page.
 * An actor allowed to see every record of the module (`<module>.viewAll`) has
 * no limit, whatever their competence (user directive 2026-11-16: an admin
 * with an ISO competence still sees every parent category). Otherwise the
 * assignment competence decides (user directive 2026-11-12): competence on
 * every category means no limit, any other competence limits the actor to the
 * roots it touches, a linked legacy root counted as its current one.
 *
 * An actor with no competence at all has no limit either: the competence is an
 * opt-in configuration, and an unconfigured actor (a newly created user) must
 * still see the records their visibility scope grants.
 */
final class ActorParentCategories
{
    public function __construct(
        private readonly ProductCategoryBranches $branches,
        private readonly LinkedCategoryGroups $groups,
    ) {}

    /**
     * The roots the actor is limited to, ascending; null when there is no limit.
     *
     * @return array<int, int>|null
     */
    public function rootIdsFor(?User $actor, ParentCategoryListModule $module): ?array
    {
        if ($actor === null || $actor->can($module->viewAllPermission())) {
            return null;
        }

        $reach = ActorCompetenceReach::for($actor);

        if ($reach->all || $reach->isEmpty()) {
            return null;
        }

        $rootIds = $this->groups->currentRootIds(array_values(array_unique(array_map($this->branches->rootIdOf(...), $reach->categoryIds))));
        sort($rootIds);

        return $rootIds;
    }
}
