<?php

namespace App\Services\Assignment;

use App\Models\EmploymentProfile;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * The ONE reading of "may this user receive this record?" (spec 0110),
 * shared by every assignment surface — the import wizard's staged rows, the
 * real leads and Gestione richieste's offers — so they can never drift apart
 * on the answer.
 *
 * The rule (INV-3): a user is competent when at least one of the record's
 * required product categories sits in the closure of their own categories
 * AND their employment business function is the EFFECTIVE function of that
 * category (spec 0023 inheritance). Two deroghe keep it from blocking work:
 *  - a record requiring NO category constrains nobody (INV-4a);
 *  - a user missing either half of the competence is a wildcard (INV-4b),
 *    so the feature stays invisible until competences are actually filled in.
 *
 * Everything is resolved in batch: the taxonomy is read once
 * (CategoryHierarchy, memoized) and the configured profiles once, then every
 * requirement is answered in memory. No query per record, no query per user.
 */
class OperatorCompetence
{
    /**
     * Mirrors CategoryHierarchy's own guard: a cycle in `parent_id` must
     * never turn into an infinite walk.
     */
    private const int MAX_DEPTH = 100;

    /** @var array<int, CompetenceProfile>|null */
    private ?array $configuredProfiles = null;

    /** @var array<int, int|null>|null */
    private ?array $effectiveFunctionByCategory = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The subset of $candidateIds competent for $requiredCategoryIds, in the
     * candidates' original order (the distributor relies on a deterministic
     * ascending order — br-balanced step 1).
     *
     * @param  array<int, int>  $candidateIds
     * @param  array<int, int>  $requiredCategoryIds
     * @return array<int, int>
     */
    public function competent(array $candidateIds, array $requiredCategoryIds): array
    {
        if ($requiredCategoryIds === [] || $candidateIds === []) {
            return array_values($candidateIds);
        }

        $excluded = $this->excludedUserIds($requiredCategoryIds);

        return array_values(array_diff($candidateIds, $excluded));
    }

    /**
     * competent() applied to MANY requirements at once — the balanced
     * distribution's own primitive, where every record may demand something
     * different. Keys are preserved, so the caller maps a record id straight
     * onto its candidate pool.
     *
     * @param  array<int, int>  $candidateIds
     * @param  array<int|string, array<int, int>>  $requirementsByKey
     * @return array<int|string, array<int, int>>
     */
    public function competentByRequirement(array $candidateIds, array $requirementsByKey): array
    {
        $result = [];

        foreach ($requirementsByKey as $key => $requiredCategoryIds) {
            $result[$key] = $this->competent($candidateIds, $requiredCategoryIds);
        }

        return $result;
    }

    /**
     * The users a record requiring $requiredCategoryIds must NOT reach: the
     * ones who configured a competence that does not cover it. Expressed as
     * an EXCLUSION on purpose — it is the only shape that stays small (the
     * wildcards of INV-4b are the majority and must never be enumerated) and
     * that a query can consume as a plain `whereNotIn` without inverting the
     * jolly rule (users/for-select, spec 0110 AC-030).
     *
     * @param  array<int, int>  $requiredCategoryIds
     * @return array<int, int>
     */
    public function excludedUserIds(array $requiredCategoryIds): array
    {
        if ($requiredCategoryIds === []) {
            return [];
        }

        $functionByCategory = $this->effectiveFunctionByCategory();

        $excluded = [];
        foreach ($this->configuredProfiles() as $userId => $profile) {
            if (! $profile->covers($requiredCategoryIds, $functionByCategory)) {
                $excluded[] = $userId;
            }
        }

        return $excluded;
    }

    /**
     * user id => profile, for the users who configured BOTH halves of the
     * competence. Everyone else is a wildcard (INV-4b) and is deliberately
     * absent: absence IS the wildcard state.
     *
     * @return array<int, CompetenceProfile>
     */
    private function configuredProfiles(): array
    {
        if ($this->configuredProfiles !== null) {
            return $this->configuredProfiles;
        }

        $profiles = EmploymentProfile::query()
            ->select(['id', 'user_id', 'business_function_id'])
            ->whereNotNull('business_function_id')
            ->whereHas('productCategories')
            ->with('productCategories:id')
            ->get();

        $this->configuredProfiles = [];

        foreach ($profiles as $profile) {
            $this->configuredProfiles[(int) $profile->user_id] = new CompetenceProfile(
                businessFunctionId: (int) $profile->business_function_id,
                categoryIds: $this->closure($profile->productCategories->pluck('id')->map(intval(...))->all()),
            );
        }

        return $this->configuredProfiles;
    }

    /**
     * category id => EFFECTIVE business function id (or null), for the whole
     * taxonomy in two queries — reuses CategoryHierarchy's own batch
     * resolver rather than walking `parent_id` a second time here.
     *
     * @return array<int, int|null>
     */
    private function effectiveFunctionByCategory(): array
    {
        return $this->effectiveFunctionByCategory ??= array_map(
            static fn (?array $summary): ?int => $summary === null ? null : (int) $summary['id'],
            $this->hierarchy->effectiveBusinessFunctionSummaries(),
        );
    }

    /**
     * $categoryIds plus every descendant (INV-2): configuring a parent makes
     * the user competent on the whole branch below it. Walks the memoized
     * `parent_id` map upward per category — cheaper than materializing a
     * children map, since a taxonomy is far wider than it is deep.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    private function closure(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $parentIdMap = $this->hierarchy->parentIdMap();
        $seeds = array_flip($categoryIds);
        $closure = $categoryIds;

        foreach (array_keys($parentIdMap) as $candidateId) {
            if (isset($seeds[$candidateId])) {
                continue;
            }

            if ($this->hasSeedAncestor($candidateId, $parentIdMap, $seeds)) {
                $closure[] = $candidateId;
            }
        }

        return $closure;
    }

    /**
     * @param  array<int, int|null>  $parentIdMap
     * @param  array<int, int>  $seeds
     */
    private function hasSeedAncestor(int $categoryId, array $parentIdMap, array $seeds): bool
    {
        $currentId = $parentIdMap[$categoryId] ?? null;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            if (isset($seeds[$currentId])) {
                return true;
            }

            $currentId = $parentIdMap[$currentId] ?? null;
            $depth++;
        }

        return false;
    }
}
