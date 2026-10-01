<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;

/**
 * The "Corsi E-Campus" branch of the Formazione root (ECampusCourseCatalogue).
 * Kept out of QualificaCatalogSeeder::CATALOG, which is two levels deep: this
 * branch is three. Every node is keyed on (name, parent).
 *
 * A subject area offered at both degree levels would otherwise read the same
 * in every select ("Ingegneria" twice), so its node carries the degree level
 * it hangs under: "<area> - <degree level>" (user directive 2026-10-01).
 *
 * The branch and its degree levels are CONTAINERS (spec 0074), the subject
 * areas the classification targets the products are filed on. Create-only:
 * an operator's later edit to a node survives a re-run.
 *
 * Seeded before CatalogRootRules::apply(), so the root's rules re-sync onto
 * the new nodes too, and before CatalogProducts, which resolves the areas
 * through area().
 */
final class ECampusCategoryTree
{
    public function seed(): void
    {
        $branch = $this->node(ECampusCourseCatalogue::CATEGORY, $this->root()->id, isSelectable: false);

        foreach (ECampusCourseCatalogue::DEGREES as $degreeName => $degree) {
            $degreeNode = $this->node($degreeName, $branch->id, isSelectable: false);

            foreach (array_keys($degree['areas']) as $areaName) {
                $this->node($this->areaNodeName($areaName, $degreeName), $degreeNode->id, isSelectable: true);
            }
        }
    }

    /**
     * A miss means the catalogue and the tree drifted apart, which must fail
     * loudly rather than silently drop an area's products.
     */
    public function area(string $degreeName, string $areaName): ProductCategory
    {
        $branch = $this->child(ECampusCourseCatalogue::CATEGORY, $this->root()->id);
        $degree = $this->child($degreeName, $branch->id);

        return $this->child($this->areaNodeName($areaName, $degreeName), $degree->id);
    }

    private function areaNodeName(string $areaName, string $degreeName): string
    {
        return sprintf('%s - %s', $areaName, $degreeName);
    }

    private function root(): ProductCategory
    {
        return ProductCategory::query()
            ->where('name', ECampusCourseCatalogue::PARENT)
            ->whereNull('parent_id')
            ->firstOrFail();
    }

    private function node(string $name, int $parentId, bool $isSelectable): ProductCategory
    {
        return ProductCategory::firstOrCreate(
            ['name' => $name, 'parent_id' => $parentId],
            ['is_selectable' => $isSelectable],
        );
    }

    private function child(string $name, int $parentId): ProductCategory
    {
        return ProductCategory::query()->where('name', $name)->where('parent_id', $parentId)->firstOrFail();
    }
}
