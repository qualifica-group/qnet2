<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Models\User;
use App\Services\Assignment\OperatorCompetence;
use App\Services\RequestManagement\RequestCategoryTabsResolver;

/**
 * The category tab favourites an operator who never saved a preference gets
 * by default (spec 0193): the strip's categories their competence covers.
 * Wildcard users and users without competence get none (D-2).
 */
final class ResolveDefaultCategoryTabPreference
{
    public function __construct(
        private readonly RequestCategoryTabsResolver $tabs,
        private readonly OperatorCompetence $competence,
    ) {}

    /**
     * @return array<int, int> category ids, ascending
     */
    public function handle(User $user, RequestModule $module): array
    {
        // Step 1: the categories on the module's strip for this actor
        $stripIds = $this->tabs->resolve($user, $module)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        // Step 2: the ones the user's competence covers
        $covered = $this->competence->coveredCategoryIdsFor((int) $user->id, $stripIds);

        sort($covered);

        return $covered;
    }
}
