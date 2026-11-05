<?php

use App\Models\ProductCategory;
use Database\Seeders\QualificaOldCategoryRemovalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function oldCategoryContainer(): ProductCategory
{
    return ProductCategory::factory()->create([
        'name' => QualificaOldCategoryRemovalSeeder::CONTAINER_NAME,
        'parent_id' => null,
        'is_active' => false,
        'is_selectable' => false,
    ]);
}

it('deactivates every category below "Old category", lifts its children to the top level and deletes it', function () {
    $container = oldCategoryContainer();
    $legacyRoot = ProductCategory::factory()->create(['name' => '[OLD] ISO', 'parent_id' => $container->id, 'old_id' => 1, 'is_active' => false]);
    $legacyChild = ProductCategory::factory()->create(['name' => '[OLD] ISO 9001', 'parent_id' => $legacyRoot->id, 'old_id' => 2, 'is_active' => false]);
    $costRoot = ProductCategory::factory()->create(['name' => '[OLD] Costi', 'parent_id' => $container->id, 'is_active' => false]);
    $costBranch = ProductCategory::factory()->create(['name' => '[OLD] Articoli', 'parent_id' => $costRoot->id, 'is_active' => true]);
    $costLeaf = ProductCategory::factory()->create(['name' => '[OLD] Veleni', 'parent_id' => $costBranch->id, 'is_active' => true]);
    $qnetCategory = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null, 'is_active' => true]);

    $this->seed(QualificaOldCategoryRemovalSeeder::class);

    expect(ProductCategory::query()->whereKey($container->id)->exists())->toBeFalse()
        ->and($legacyRoot->fresh()->parent_id)->toBeNull()
        ->and($costRoot->fresh()->parent_id)->toBeNull()
        // The hierarchy below the children keeps its shape.
        ->and($legacyChild->fresh()->parent_id)->toBe($legacyRoot->id)
        ->and($costLeaf->fresh()->parent_id)->toBe($costBranch->id)
        // Inactive on their own flag, at every depth.
        ->and(ProductCategory::query()->whereKey([$legacyRoot->id, $legacyChild->id, $costRoot->id, $costBranch->id, $costLeaf->id])->where('is_active', true)->exists())->toBeFalse()
        // A category outside the container is left alone.
        ->and($qnetCategory->fresh()->is_active)->toBeTrue();
});

it('does nothing without the "Old category" container, and a second run changes nothing', function () {
    $container = oldCategoryContainer();
    $legacyRoot = ProductCategory::factory()->create(['name' => '[OLD] GDPR', 'parent_id' => $container->id, 'old_id' => 9, 'is_active' => false]);
    $unrelated = ProductCategory::factory()->create(['name' => 'Old category', 'parent_id' => $legacyRoot->id, 'is_active' => true]);

    $this->seed(QualificaOldCategoryRemovalSeeder::class);
    $this->seed(QualificaOldCategoryRemovalSeeder::class);

    // A nested node with the same name is not the container: deactivated as a
    // descendant on the first run, never deleted.
    expect($legacyRoot->fresh()->parent_id)->toBeNull()
        ->and($unrelated->fresh()->parent_id)->toBe($legacyRoot->id)
        ->and($unrelated->fresh()->is_active)->toBeFalse()
        ->and(ProductCategory::query()->count())->toBe(2);
});
