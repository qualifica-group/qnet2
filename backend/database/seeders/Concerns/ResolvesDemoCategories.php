<?php

namespace Database\Seeders\Concerns;

use App\Models\ProductCategory;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use RuntimeException;

/**
 * Resolves a demo category by its POSITION IN THE DEMO TREE — a root by name
 * plus `parent_id IS NULL`, a child by name plus its own root's id — never by
 * name alone.
 *
 * `product_categories.name` is not unique across the table: the same label
 * legitimately exists in another branch (a database carrying the client
 * catalogue has "CONSULENZA IT" under its own "Consulenza" root), and on
 * MySQL's case-insensitive collation even a differently-cased row matches.
 * A seeder resolving by name alone then ADOPTS that foreign category:
 * `firstOrCreate` never creates the demo leaf and every downstream step
 * (attributes, layouts, products, workflows) is written against the client's
 * row — DemoProductSeeder rejecting `demo_consulting_days` as "not part of
 * the applicable attribute set" is the symptom, since the adopted category
 * carries the client's attributes, not the demo ones.
 */
trait ResolvesDemoCategories
{
    protected function demoCategory(string $categoryName): ?ProductCategory
    {
        $rootName = DemoCategoryCatalogue::branchOf($categoryName);

        $root = ProductCategory::query()
            ->where('name', $rootName)
            ->whereNull('parent_id')
            ->first();

        if ($root === null || $rootName === $categoryName) {
            return $root;
        }

        return ProductCategory::query()
            ->where('name', $categoryName)
            ->where('parent_id', $root->id)
            ->first();
    }

    protected function demoCategoryOrFail(string $categoryName): ProductCategory
    {
        return $this->demoCategory($categoryName)
            ?? throw new RuntimeException("Demo category \"{$categoryName}\" not found: run DemoProductCategorySeeder first.");
    }
}
