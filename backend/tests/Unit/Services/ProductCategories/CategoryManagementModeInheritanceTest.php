<?php

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryManagementModeInheritance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('categoryManagementModeInheritance')) {
    function categoryManagementModeInheritance(): CategoryManagementModeInheritance
    {
        return new CategoryManagementModeInheritance(new CategoryHierarchy);
    }
}

// ---------------------------------------------------------------------------
// schema + default (AC-006)
// ---------------------------------------------------------------------------

it('management_mode is a string column defaulting to multiple', function () {
    expect(Schema::hasColumn('product_categories', 'management_mode'))->toBeTrue();

    expect(ProductCategory::factory()->create()->fresh()->management_mode)->toBe(CategoryManagementMode::Multiple);
});

it('down() reverses the migration, up() recreates the column', function () {
    $migration = require database_path('migrations/2026_08_03_150000_add_management_mode_to_product_categories_table.php');

    $migration->down();
    expect(Schema::hasColumn('product_categories', 'management_mode'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('product_categories', 'management_mode'))->toBeTrue();
});

it('casts management_mode to the CategoryManagementMode enum', function () {
    $category = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);

    expect($category->fresh()->management_mode)->toBe(CategoryManagementMode::Single);
});

// ---------------------------------------------------------------------------
// inheritedValueFor / sourceCategoryFor — value ereditato dalla radice
// ---------------------------------------------------------------------------

it('inheritedValueFor returns null for a root (nothing to inherit)', function () {
    expect(categoryManagementModeInheritance()->inheritedValueFor(null))->toBeNull();
});

it('inheritedValueFor returns the ROOT value for a direct child', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);

    expect(categoryManagementModeInheritance()->inheritedValueFor($root->id))->toBe(CategoryManagementMode::Single);
});

it('inheritedValueFor returns the ROOT value for a grandchild, not an intermediate ancestor', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => CategoryManagementMode::Single]);

    expect(categoryManagementModeInheritance()->inheritedValueFor($child->id))->toBe(CategoryManagementMode::Single);
});

it('sourceCategoryFor is null for a root and points to the root for a descendant', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'management_mode' => CategoryManagementMode::Single]);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => CategoryManagementMode::Single]);

    expect(categoryManagementModeInheritance()->sourceCategoryFor($root))->toBeNull();
    expect(categoryManagementModeInheritance()->sourceCategoryFor($child))
        ->toBe(['id' => $root->id, 'name' => 'Formazione']);
});

// ---------------------------------------------------------------------------
// syncSubtree — sync su piu' livelli (AC-004)
// ---------------------------------------------------------------------------

it('syncSubtree re-aligns descendants across MULTIPLE levels on a root flip', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Multiple]);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => CategoryManagementMode::Multiple]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['management_mode' => CategoryManagementMode::Multiple]);

    $root->update(['management_mode' => CategoryManagementMode::Single]);
    categoryManagementModeInheritance()->syncSubtree($root);

    expect($child->fresh()->management_mode)->toBe(CategoryManagementMode::Single)
        ->and($grandchild->fresh()->management_mode)->toBe(CategoryManagementMode::Single);
});

it('syncSubtree is a no-op when the subtree already holds the effective value', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => CategoryManagementMode::Single]);

    categoryManagementModeInheritance()->syncSubtree($root);

    expect($child->fresh()->updated_at)->toEqual($child->updated_at);
});

// ---------------------------------------------------------------------------
// reparenting — cambia la radice, cambia la modalita' (AC-002/004/005/006)
// ---------------------------------------------------------------------------

it('reparenting a branch under a different-mode root realigns the WHOLE moved subtree (AC-005)', function () {
    $singleRoot = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $multipleRoot = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Multiple]);
    $moved = ProductCategory::factory()->childOf($multipleRoot)->create(['management_mode' => CategoryManagementMode::Multiple]);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['management_mode' => CategoryManagementMode::Multiple]);

    $moved->update(['parent_id' => $singleRoot->id]);
    categoryManagementModeInheritance()->syncSubtree($moved);

    expect($moved->fresh()->management_mode)->toBe(CategoryManagementMode::Single)
        ->and($movedChild->fresh()->management_mode)->toBe(CategoryManagementMode::Single)
        ->and($multipleRoot->fresh()->management_mode)->toBe(CategoryManagementMode::Multiple);
});

it('a child inherits the root value even when created without submitting one (AC-002)', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $child = ProductCategory::factory()->childOf($root)->create();

    categoryManagementModeInheritance()->syncSubtree($child);

    expect($child->fresh()->management_mode)->toBe(CategoryManagementMode::Single);
});

it('a pre-existing root defaults to multiple and changes nothing at deploy (AC-006)', function () {
    $root = ProductCategory::factory()->create();

    expect($root->fresh()->management_mode)->toBe(CategoryManagementMode::Multiple);
});
