<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Folds the retired three-level e-Campus tree onto "Corsi E-Campus" (user
 * directive 2026-10-02). An earlier revision of the catalogue split the
 * branch into one container per degree level, each parenting one selectable
 * "<area> - <degree level>" node the products were filed on; every product
 * now sits on the branch itself (ECampusCourseCatalogue).
 *
 * On an installation that still has the old nodes, everything pointing at
 * them moves onto the branch — the products themselves, so CatalogProducts
 * finds them by (name, category) instead of duplicating them, and every row
 * a category delete would refuse — then the emptied nodes go. A no-op on a
 * fresh install. Runs in one transaction: a fold that cannot complete leaves
 * the old tree whole.
 */
final class ECampusTreeFlattening
{
    /**
     * The degree-level containers of the retired tree, children of the branch.
     *
     * @var list<string>
     */
    private const array RETIRED_DEGREES = [
        'Corsi di Laurea Triennali',
        'Corsi di Laurea Magistrali',
    ];

    /**
     * Every column restricting a category delete: table => column.
     *
     * @var array<string, string>
     */
    private const array REFERENCES = [
        'products' => 'category_id',
        'opportunity_product_lines' => 'product_category_id',
        'project_product_lines' => 'product_category_id',
        'campaign_product_lines' => 'product_category_id',
        'employment_product_lines' => 'product_category_id',
        'commission_configurations' => 'product_category_id',
    ];

    /**
     * The product line tables, unique on (owner, function, category): table =>
     * owner column. Two lines of one owner on two retired areas would become
     * the same line on the branch.
     *
     * @var array<string, string>
     */
    private const array UNIQUE_LINES = [
        'opportunity_product_lines' => 'opportunity_id',
        'project_product_lines' => 'project_id',
        'campaign_product_lines' => 'campaign_id',
        'employment_product_lines' => 'employment_profile_id',
    ];

    public function apply(): void
    {
        $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->first();

        if ($branch === null) {
            return;
        }

        $degrees = ProductCategory::query()
            ->where('parent_id', $branch->id)
            ->whereIn('name', self::RETIRED_DEGREES)
            ->get();

        if ($degrees->isEmpty()) {
            return;
        }

        $areas = ProductCategory::query()->whereIn('parent_id', $degrees->modelKeys())->get();

        DB::transaction(function () use ($branch, $degrees, $areas): void {
            // Step 1: refuse a fold that would merge two product lines into one.
            $this->assertNoMergedLines($branch, $areas->modelKeys());
            // Step 2: repoint every reference onto the branch.
            $this->repoint($branch, [...$degrees->modelKeys(), ...$areas->modelKeys()]);
            // Step 3: drop the emptied nodes, leaves first (parent_id restricts).
            $this->delete($areas);
            $this->delete($degrees);
        });
    }

    /**
     * @param  list<int>  $areaIds
     */
    private function assertNoMergedLines(ProductCategory $branch, array $areaIds): void
    {
        foreach (self::UNIQUE_LINES as $table => $owner) {
            $merged = DB::table($table)
                ->whereIn('product_category_id', [...$areaIds, $branch->id])
                ->groupBy($owner, 'business_function_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($merged) {
                throw new RuntimeException(sprintf(
                    'Cannot fold the e-Campus tree: two rows of "%s" would become one on "%s". Merge them by hand first.',
                    $table,
                    $branch->name,
                ));
            }
        }
    }

    /**
     * @param  list<int>  $retiredIds
     */
    private function repoint(ProductCategory $branch, array $retiredIds): void
    {
        foreach (self::REFERENCES as $table => $column) {
            DB::table($table)->whereIn($column, $retiredIds)->update([$column => $branch->id]);
        }
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function delete(Collection $categories): void
    {
        $categories->each(fn (ProductCategory $category): ?bool => $category->delete());
    }
}
