<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Models\User;
use App\Services\Assignment\OperatorCompetence;

/**
 * The category tab favourites an operator who never saved a preference gets
 * by default (spec 0193): the strip's categories their competence covers.
 * Wildcard users and users without competence get none (D-2). The strip is
 * handed in as ids, so every grid with a tab strip shares the rule (spec 0335).
 */
final class ResolveDefaultCategoryTabPreference
{
    public function __construct(private readonly OperatorCompetence $competence) {}

    /**
     * @param  array<int, int>  $stripCategoryIds  the categories on the strip for this actor
     * @return array<int, int> category ids, ascending
     */
    public function handle(User $user, array $stripCategoryIds): array
    {
        $covered = $this->competence->coveredCategoryIdsFor((int) $user->id, $stripCategoryIds);

        sort($covered);

        return $covered;
    }
}
