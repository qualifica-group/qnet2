<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Database\Seeders\QualificaCatalog\CatalogProducts;
use Database\Seeders\QualificaCatalog\CatalogRootRules;
use Database\Seeders\QualificaCatalog\ProjectDesignCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Adds the "Progettazione" branch (ProjectDesignCatalogue) to an installation
 * that is already live and can no longer be reseeded (user directive
 * 2026-11-03): `php artisan db:seed --class=QualificaProjectDesignCatalogSeeder`.
 * A fresh install gets the same branch from QualificaCatalogSeeder, step 2 of
 * QualificaProductionDataSeeder; this seeder is not part of that chain.
 *
 * Touches the "Progettazione" branch only, never the rest of the catalogue:
 *   - the root and its subcategory are CREATED when missing and left
 *     as they are otherwise (no realignment of an operator's edit);
 *   - the root's business function is linked only if its slot is free;
 *   - the Consulenza rules are written on this root and its subtree alone;
 *   - the products are created when missing, an existing one only has its
 *     blanks filled (BranchProducts).
 *
 * A category of the branch's name found ELSEWHERE in the tree stops the run
 * instead of being moved, and the transaction rolls back whatever was
 * written. Idempotent: a re-run creates nothing.
 */
class QualificaProjectDesignCatalogSeeder extends Seeder
{
    use SeedsWithoutMail;

    public function run(): void
    {
        $this->withoutMail(fn () => DB::transaction($this->seedBranch(...)));
    }

    private function seedBranch(): void
    {
        // Step 1: the root and its subcategory.
        $root = $this->ensureCategory(ProjectDesignCatalogue::ROOT, null, isSelectable: false);

        foreach (array_keys(ProjectDesignCatalogue::SUBCATEGORIES) as $name) {
            $this->ensureCategory($name, $root->id, isSelectable: true);
        }

        // Step 2: the business function, on the root alone.
        $this->callWith(QualificaBusinessFunctionLinkSeeder::class, ['categoryNames' => [ProjectDesignCatalogue::ROOT]]);

        // Step 3: the root-owned rules, re-synced on this subtree only.
        app(CatalogRootRules::class)->applyToRoot(ProjectDesignCatalogue::ROOT);

        // Step 4: the products, which resolve the categories of step 1.
        app(CatalogProducts::class)->seedProjectDesignProducts();
    }

    /**
     * The name is the catalogue's natural key, matched at any depth.
     */
    private function ensureCategory(string $name, ?int $parentId, bool $isSelectable): ProductCategory
    {
        /** @var ProductCategory $category */
        $category = ProductCategory::firstOrCreate(
            ['name' => $name],
            ['parent_id' => $parentId, 'is_selectable' => $isSelectable, 'is_reportable' => null],
        );

        if ($category->parent_id !== $parentId) {
            throw new RuntimeException(sprintf('Category "%s" already exists elsewhere in the catalogue (id %d): nothing was seeded.', $name, $category->id));
        }

        if ($category->wasRecentlyCreated) {
            $this->command?->info(sprintf('Category "%s" created.', $name));
        }

        return $category;
    }
}
