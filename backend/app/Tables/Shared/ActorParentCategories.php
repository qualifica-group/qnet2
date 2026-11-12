<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\User;
use App\Products\ProductCategoryBranches;
use App\Services\Assignment\ActorCompetenceReach;

/**
 * The parent categories ("Categoria madre") an actor works on, read from their
 * assignment competence (user directive 2026-11-12): an actor whose competence
 * covers every category has no limit, any other actor is limited to the roots
 * their competence touches, a linked legacy root counted as its current one.
 *
 * An actor with no competence at all has no limit either: the competence is an
 * opt-in configuration, and an unconfigured actor (a super-admin, a newly
 * created user) must still see the records their visibility scope grants.
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
    public function rootIdsFor(?User $actor): ?array
    {
        $reach = $actor === null ? null : ActorCompetenceReach::for($actor);

        if ($reach === null || $reach->all || $reach->isEmpty()) {
            return null;
        }

        $rootIds = $this->groups->currentRootIds(array_values(array_unique(array_map($this->branches->rootIdOf(...), $reach->categoryIds))));
        sort($rootIds);

        return $rootIds;
    }
}
