<?php

namespace App\Migrations\Support;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * Keeps the qnet catalogue's own "APL" branch apart from its legacy twin (user
 * directive 2026-10-05, the category-level counterpart of "FORMAZIONE OLD" in
 * CategoryBusinessFunctionLinker).
 *
 * The legacy sends every category name behind LEGACY_PREFIX (user directive
 * 2026-10-13), so its APL tree arrives as LEGACY_BRANCH and never shares a
 * name with the manual one. It hangs where every other legacy node does,
 * under "Consulenza" like the FORMAZIONE OLD ones (user directive
 * 2026-10-05), and nothing in its branch is selectable. Its products stay
 * on it, history only (user directive 2026-10-13).
 */
final class LegacyAplBranch
{
    /** Root of the manual branch QualificaCatalogSeeder seeds. */
    public const string MANUAL_ROOT = 'APL';

    /** What the legacy puts before the name of every category it sends. */
    private const string LEGACY_PREFIX = '[OLD] ';

    /** The legacy twin of MANUAL_ROOT: the top of the legacy APL branch. */
    public const string LEGACY_BRANCH = self::LEGACY_PREFIX.self::MANUAL_ROOT;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * Makes every node of the LEGACY_BRANCH branch among $createdIds a container:
     * the legacy APL tree is never a classification target (user directive
     * 2026-10-05). Scoped to the nodes the caller just created, so an
     * operator's later choice on an older node is never undone. Per-model
     * updates, so the activity log records them.
     *
     * @param  list<int>  $createdIds
     */
    public function closeLegacyBranch(array $createdIds): void
    {
        $top = $this->legacyBranchTop();

        if ($top === null || $createdIds === []) {
            return;
        }

        $branchIds = [$top->id, ...$this->hierarchy->descendantIds($top->id)];

        ProductCategory::query()
            ->whereIntegerInRaw('id', array_intersect($branchIds, $createdIds))
            ->where('is_selectable', true)
            ->get()
            ->each(fn (ProductCategory $category) => $category->update(['is_selectable' => false]));
    }

    /**
     * The imported LEGACY_BRANCH node, wherever the import nested it.
     */
    private function legacyBranchTop(): ?ProductCategory
    {
        return ProductCategory::query()->where('name', self::LEGACY_BRANCH)->whereNotNull('old_id')->first();
    }
}
