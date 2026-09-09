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
 * The rule (spec 0111 D-2): a user is competent for a record requiring a
 * category C when at least ONE of their competence ROWS (function F,
 * category K) has C inside K's descendant closure AND F equal to the
 * EFFECTIVE business function of C (spec 0023 inheritance). Per row, not per
 * profile: two rows on two different functions make the same user competent
 * on both sides. Two deroghe keep the rule from blocking work:
 *  - a record requiring NO category constrains nobody (INV-4a);
 *  - a user with NO row is a wildcard (INV-4b), so the feature stays
 *    invisible until competences are actually filled in.
 *
 * Everything is resolved in batch: the taxonomy is read once
 * (CategoryHierarchy, memoized) and the configured profiles once, then every
 * requirement is answered in memory. No query per record, no query per user.
 */
class OperatorCompetence
{
    /** @var array<int, CompetenceProfile>|null */
    private ?array $configuredProfiles = null;

    /** @var array<int, int|null>|null */
    private ?array $effectiveFunctionByCategory = null;

    /**
     * Memo of childIdMap()'s single inversion of the parent map.
     *
     * @var array<int, array<int, int>>|null
     */
    private ?array $childIdMap = null;

    /**
     * Memo of closureOf(), keyed by SEED category: the same category is the
     * seed of many rows and of many users, and the walk must not be repeated
     * per row (the batch would degrade quadratically).
     *
     * @var array<int, array<int, int>>
     */
    private array $closureBySeed = [];

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
     * ones whose competence rows do not cover it. Expressed as an EXCLUSION
     * on purpose — it is the only shape that stays small (the wildcards of
     * INV-4b are the majority and must never be enumerated) and that a query
     * can consume as a plain `whereNotIn` without inverting the jolly rule
     * (users/for-select, spec 0110 AC-030).
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
     * user id => profile, for the users carrying at least one competence row
     * (spec 0111). Everyone else is a wildcard (INV-4b) and is deliberately
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
            ->select(['id', 'user_id'])
            ->whereHas('productLines')
            ->with('productLines:id,employment_profile_id,business_function_id,product_category_id')
            ->get();

        $this->configuredProfiles = [];

        foreach ($profiles as $profile) {
            $this->configuredProfiles[(int) $profile->user_id] = new CompetenceProfile(
                coveredCategoryIdsByFunction: $this->coveredCategoryIdsByFunction($profile),
            );
        }

        return $this->configuredProfiles;
    }

    /**
     * The profile's rows folded into "business function id => covered
     * category id => true": rows sharing a function merge their closures,
     * which is exactly what covers() asks of them.
     *
     * @return array<int, array<int, true>>
     */
    private function coveredCategoryIdsByFunction(EmploymentProfile $profile): array
    {
        $covered = [];

        foreach ($profile->productLines as $line) {
            $functionId = (int) $line->business_function_id;

            foreach ($this->closureOf((int) $line->product_category_id) as $categoryId) {
                $covered[$functionId][$categoryId] = true;
            }
        }

        return $covered;
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
     * $categoryId plus every descendant (INV-2): a row on a parent makes the
     * user competent on the whole branch below it. Memoized per seed, since
     * the same category seeds many rows across many users.
     *
     * @return array<int, int>
     */
    private function closureOf(int $categoryId): array
    {
        return $this->closureBySeed[$categoryId] ??= $this->walkDescendants($categoryId);
    }

    /**
     * Breadth-first walk down the children index. The visited set doubles as
     * the anti-cycle guard: a corrupted `parent_id` loop revisits a known id
     * and stops, it never turns into an infinite walk.
     *
     * @return array<int, int>
     */
    private function walkDescendants(int $categoryId): array
    {
        $childIdMap = $this->childIdMap();
        $visited = [$categoryId => true];
        $closure = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            foreach ($childIdMap[$currentId] ?? [] as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $closure[] = $childId;
                $queue[] = $childId;
            }
        }

        return $closure;
    }

    /**
     * parent id => child ids, inverted once from CategoryHierarchy's memoized
     * `parent_id` projection. Materialized here (unlike the per-category
     * upward walk this replaces) because the closure is now resolved per ROW:
     * scanning the whole taxonomy once per row would be quadratic.
     *
     * @return array<int, array<int, int>>
     */
    private function childIdMap(): array
    {
        if ($this->childIdMap !== null) {
            return $this->childIdMap;
        }

        $this->childIdMap = [];

        foreach ($this->hierarchy->parentIdMap() as $categoryId => $parentId) {
            if ($parentId !== null) {
                $this->childIdMap[$parentId][] = $categoryId;
            }
        }

        return $this->childIdMap;
    }
}
