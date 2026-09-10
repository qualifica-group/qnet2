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
 * on both sides.
 *
 * Spec 0111 rev.2 (D-9) revoked the one deroga that used to bend this: a user
 * with NO competence row is no longer a wildcard, they are competent for
 * NOTHING. Only INV-4a survives (D-9a): a record requiring no category
 * constrains nobody, since there is nothing there to filter on.
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

        return array_values(array_intersect($candidateIds, $this->competentUserIds($requiredCategoryIds)));
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
     * The users a record requiring $requiredCategoryIds MAY reach: the ones
     * whose competence rows cover it. An INCLUSION since spec 0111 rev.2
     * revoked the jolly deroga (D-9b) — the competent set is now a subset of
     * the CONFIGURED users, so it is small by construction and a query can
     * consume it as a plain `whereIn` (users/for-select, AC-016 rev.2). Under
     * the old rule the same shape would have had to enumerate every wildcard,
     * which is why it used to be expressed the other way round.
     *
     * INV-4a is not decided here: with no required category nothing is
     * covered and this answers the empty set, so a caller wanting "requiring
     * nothing constrains nobody" (D-9a) short-circuits before asking.
     *
     * @param  array<int, int>  $requiredCategoryIds
     * @return array<int, int>
     */
    public function competentUserIds(array $requiredCategoryIds): array
    {
        $functionByCategory = $this->effectiveFunctionByCategory();

        $competent = [];
        foreach ($this->configuredProfiles() as $userId => $profile) {
            if ($profile->covers($requiredCategoryIds, $functionByCategory)) {
                $competent[] = $userId;
            }
        }

        return $competent;
    }

    /**
     * user id => profile, for the users carrying at least one competence row
     * (spec 0111). Everyone else is deliberately absent, and since rev.2
     * absence means NOT A CANDIDATE (D-9), no longer "competent for
     * everything": the whole population of competent users lives in here.
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
