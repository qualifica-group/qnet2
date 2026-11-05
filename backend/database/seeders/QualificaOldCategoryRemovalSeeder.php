<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Removes the "Old category" container the legacy import used to gather its
 * categories under (user directive 2026-11-05, spec 0013 amendment sexies):
 * `php artisan db:seed --class=QualificaOldCategoryRemovalSeeder`.
 *
 * Every category below the container becomes NOT active on its own flag, not
 * only through the inactive ancestor (spec 0208), so lifting the container's
 * children to the top level never reactivates a branch. Then the container,
 * left empty, is deleted.
 *
 * Per-model writes, so the activity log records each change. One transaction,
 * and idempotent: without the container it does nothing.
 */
class QualificaOldCategoryRemovalSeeder extends Seeder
{
    /** The container's name, as the removed LegacyCategoryContainer created it. */
    public const string CONTAINER_NAME = 'Old category';

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function run(): void
    {
        $container = ProductCategory::query()->where('name', self::CONTAINER_NAME)->whereNull('parent_id')->first();

        if ($container === null) {
            $this->command?->info(sprintf('"%s" not found: nothing to do.', self::CONTAINER_NAME));

            return;
        }

        DB::transaction(function () use ($container): void {
            // Step 1: every node below the container is inactive on its own.
            $deactivated = $this->deactivateDescendants($container);

            // Step 2: the container's children become roots.
            $lifted = $this->liftChildren($container);

            // Step 3: the empty container goes.
            $container->delete();

            $this->command?->info(sprintf('"%s" removed: %d categories deactivated, %d moved to the top level.', self::CONTAINER_NAME, $deactivated, $lifted));
        });
    }

    private function deactivateDescendants(ProductCategory $container): int
    {
        $active = ProductCategory::query()
            ->whereIntegerInRaw('id', $this->hierarchy->descendantIds($container->id))
            ->where('is_active', true)
            ->get();

        $active->each(fn (ProductCategory $category) => $category->update(['is_active' => false]));

        return $active->count();
    }

    private function liftChildren(ProductCategory $container): int
    {
        $children = ProductCategory::query()->where('parent_id', $container->id)->get();

        $children->each(fn (ProductCategory $category) => $category->update(['parent_id' => null]));

        return $children->count();
    }
}
