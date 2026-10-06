<?php

namespace App\Migrations\Support;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * Keeps the qnet catalogue's own "APL" branch apart from its legacy twin (user
 * directive 2026-10-05, the category-level counterpart of "FORMAZIONE OLD" in
 * CategoryBusinessFunctionLinker).
 *
 * The legacy APL tree is no longer ADOPTED into the manual one: a legacy
 * record named like a node of the manual branch is imported beside it, under
 * that name plus LEGACY_SUFFIX, so the two never share a name in a select. The
 * twin of the manual ROOT stays a root of its own ("APL old"), never under
 * "Consulenza", and nothing in that branch is selectable. The products the legacy files on that tree land on the manual
 * categories replacing the legacy ones (productCategoryFor).
 */
final class LegacyAplBranch
{
    /** Root of the manual branch QualificaCatalogSeeder seeds. */
    public const string MANUAL_ROOT = 'APL';

    private const string LEGACY_SUFFIX = ' old';

    /** The legacy twin of MANUAL_ROOT, imported as a root of its own. */
    public const string LEGACY_ROOT = self::MANUAL_ROOT.self::LEGACY_SUFFIX;

    /**
     * Legacy category name => the manual category its products are filed on.
     * The targets are bound by identity to QualificaCatalogSeeder::CATALOG:
     * a test asserts each one is seeded under MANUAL_ROOT.
     *
     * @var array<string, string>
     */
    public const array PRODUCT_CATEGORIES = [
        'Formazione Apprendistato' => 'Apprendistato',
        'Tirocini extracurriculari privati' => 'Tirocinio',
        'Orientamento Specialistico' => 'Orientamento specialistico',
    ];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The node of the manual branch named $name, which no legacy record may
     * adopt. Compared case-insensitively in PHP, so the twin is recognised
     * the same way whatever the column collation ("Orientamento Specialistico"
     * is the legacy twin of "Orientamento specialistico").
     */
    public function manualNodeNamed(string $name): ?ProductCategory
    {
        $root = ProductCategory::query()
            ->where('name', self::MANUAL_ROOT)
            ->whereNull('parent_id')
            ->whereNull('old_id')
            ->first();

        if ($root === null) {
            return null;
        }

        return ProductCategory::query()
            ->whereKey([$root->id, ...$this->hierarchy->descendantIds($root->id)])
            ->get()
            ->first(static fn (ProductCategory $node): bool => strcasecmp($node->name, $name) === 0);
    }

    /**
     * Makes every node of the "APL old" branch among $createdIds a container:
     * the legacy APL tree is never a classification target (user directive
     * 2026-10-05). Scoped to the nodes the caller just created, so an
     * operator's later choice on an older node is never undone. Per-model
     * updates, so the activity log records them.
     *
     * @param  list<int>  $createdIds
     */
    public function closeLegacyBranch(array $createdIds): void
    {
        $root = ProductCategory::query()
            ->where('name', self::LEGACY_ROOT)
            ->whereNull('parent_id')
            ->whereNotNull('old_id')
            ->first();

        if ($root === null || $createdIds === []) {
            return;
        }

        $branchIds = [$root->id, ...$this->hierarchy->descendantIds($root->id)];

        ProductCategory::query()
            ->whereIntegerInRaw('id', array_intersect($branchIds, $createdIds))
            ->where('is_selectable', true)
            ->get()
            ->each(fn (ProductCategory $category) => $category->update(['is_selectable' => false]));
    }

    /**
     * The name the legacy twin of the manual node $name is imported under.
     */
    public function legacyName(string $name): string
    {
        return $name.self::LEGACY_SUFFIX;
    }

    /**
     * The category a product the legacy files on $categoryId is imported on:
     * the manual replacement when $categoryId sits in the legacy APL branch,
     * $categoryId itself otherwise. A legacy node with no replacement keeps
     * its product, with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    public function productCategoryFor(int $categoryId, array &$warnings): int
    {
        $category = ProductCategory::query()->findOrFail($categoryId);
        $root = $this->rootOf($category);

        if ($root->old_id === null || strcasecmp($root->name, self::LEGACY_ROOT) !== 0) {
            return $categoryId;
        }

        $targetId = $this->replacementFor($category->name);

        if ($targetId === null) {
            $warnings[] = sprintf('Category "%s" of the legacy APL branch has no manual replacement: product left on it.', $category->name);

            return $categoryId;
        }

        return $targetId;
    }

    private function replacementFor(string $importedName): ?int
    {
        // A twin carries LEGACY_SUFFIX; the map is keyed on the legacy name.
        $legacyName = str_ends_with($importedName, self::LEGACY_SUFFIX)
            ? substr($importedName, 0, -strlen(self::LEGACY_SUFFIX))
            : $importedName;

        foreach (self::PRODUCT_CATEGORIES as $legacy => $manual) {
            if (strcasecmp($legacyName, $legacy) === 0) {
                /** @var int|null $id */
                $id = ProductCategory::query()->where('name', $manual)->whereNull('old_id')->value('id');

                return $id;
            }
        }

        return null;
    }

    private function rootOf(ProductCategory $category): ProductCategory
    {
        return $this->hierarchy->ancestors($category)->first() ?? $category;
    }
}
