<?php

namespace App\Services\Assignment;

/**
 * One user's CONFIGURED assignment competence (spec 0110): the single
 * business function on their employment profile, plus the closure of the
 * product categories they are operative on (their own categories AND every
 * descendant — INV-2, mirroring the way a category inherits its function
 * from an ancestor in spec 0023).
 *
 * Only users who configured BOTH halves get a profile: a user missing either
 * one is a wildcard (INV-4b) and never reaches this object, so there is no
 * "unconfigured" state to represent here.
 */
final readonly class CompetenceProfile
{
    /**
     * @param  array<int, int>  $categoryIds  the descendant closure, not the raw pivot rows
     */
    public function __construct(
        public int $businessFunctionId,
        public array $categoryIds,
    ) {}

    /**
     * Whether this user covers at least ONE of the required categories with
     * the matching business function (INV-3). `$functionByCategory` maps a
     * category id to its EFFECTIVE business function id (null when the
     * category's chain carries none — in which case the function half has
     * nothing to compare against and the category alone decides).
     *
     * @param  array<int, int>  $requiredCategoryIds
     * @param  array<int, int|null>  $functionByCategory
     */
    public function covers(array $requiredCategoryIds, array $functionByCategory): bool
    {
        foreach ($requiredCategoryIds as $categoryId) {
            if (! in_array($categoryId, $this->categoryIds, true)) {
                continue;
            }

            $requiredFunctionId = $functionByCategory[$categoryId] ?? null;

            if ($requiredFunctionId === null || $requiredFunctionId === $this->businessFunctionId) {
                return true;
            }
        }

        return false;
    }
}
