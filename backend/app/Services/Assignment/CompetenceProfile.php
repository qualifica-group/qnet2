<?php

namespace App\Services\Assignment;

/**
 * One user's CONFIGURED assignment competence (spec 0111 D-2): the ROWS of
 * their employment profile, each pairing a business function with a product
 * category, projected as business function id => the descendant closure of
 * the categories that function covers (INV-2: configuring a parent covers
 * the whole branch below it, mirroring the way a category inherits its
 * function from an ancestor in spec 0023).
 *
 * Per ROW, not per profile: the single `business_function_id` column of spec
 * 0110 is gone (D-1), so a user with two rows on two different functions is
 * competent on both sides — something the previous shape could not express.
 *
 * Spec 0129 adds two more ways to widen this beyond enumerated categories:
 *  - $allProductCategories (D-1): the profile's own wildcard flag, true for
 *    ANY required category regardless of function — checked first, since it
 *    makes every other field on this object moot;
 *  - a row with a null category (D-3, folded into $wildcardFunctionIds by
 *    OperatorCompetence rather than into $coveredCategoryIdsByFunction):
 *    "every category of this row's function" — covers a required category
 *    ONLY when that category's OWN effective function matches, never one
 *    with no effective function at all (unlike the flag above).
 *
 * Only users with the flag or at least one row get a profile: since spec
 * 0111 rev.2 revoked the jolly deroga (D-9) a user with neither is competent
 * for nothing, so they never reach this object and there is no
 * "unconfigured" state to represent here.
 */
final readonly class CompetenceProfile
{
    /**
     * @param  array<int, array<int, true>>  $coveredCategoryIdsByFunction  business function id => covered category id => true. The inner map is keyed by id (not a list) because covers() is on the hot path of every assignment batch and must answer in O(1).
     * @param  array<int, true>  $wildcardFunctionIds  business function id => true, for a (function, null) row (spec 0129 D-3): covers every category whose EFFECTIVE function is that one.
     */
    public function __construct(
        public array $coveredCategoryIdsByFunction,
        public array $wildcardFunctionIds = [],
        public bool $allProductCategories = false,
    ) {}

    /**
     * Whether at least ONE of the required categories is covered — by the
     * profile-wide wildcard flag (spec 0129 D-1, checked first as it decides
     * everything unconditionally), by a (function, null) row matching the
     * category's effective function (D-3), or by ONE of this user's rows
     * carrying the matching business function and category (spec 0111 D-2).
     * `$functionByCategory` maps a category id to its EFFECTIVE business
     * function id (null when the category's chain carries none — in which
     * case the function half has nothing to compare against and the category
     * coverage alone decides).
     *
     * @param  array<int, int>  $requiredCategoryIds
     * @param  array<int, int|null>  $functionByCategory
     */
    public function covers(array $requiredCategoryIds, array $functionByCategory): bool
    {
        if ($this->allProductCategories) {
            return true;
        }

        foreach ($requiredCategoryIds as $categoryId) {
            $requiredFunctionId = $functionByCategory[$categoryId] ?? null;

            if ($requiredFunctionId === null) {
                if ($this->coversUnderAnyFunction($categoryId)) {
                    return true;
                }

                continue;
            }

            if (isset($this->wildcardFunctionIds[$requiredFunctionId])) {
                return true;
            }

            if (isset($this->coveredCategoryIdsByFunction[$requiredFunctionId][$categoryId])) {
                return true;
            }
        }

        return false;
    }

    private function coversUnderAnyFunction(int $categoryId): bool
    {
        foreach ($this->coveredCategoryIdsByFunction as $coveredCategoryIds) {
            if (isset($coveredCategoryIds[$categoryId])) {
                return true;
            }
        }

        return false;
    }
}
