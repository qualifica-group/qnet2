<?php

use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// Spec 0374, D-11: the one-off backfill of product_categories.current_category_id (AC-023).

uses(RefreshDatabase::class);

function currentCategoryMigration(): Migration
{
    return require database_path('migrations/2026_12_02_100000_add_current_category_id_to_product_categories_table.php');
}

function runCurrentCategoryBackfill(): void
{
    $migration = currentCategoryMigration();
    (new ReflectionMethod($migration, 'linkByName'))->invoke($migration);
}

function legacyRootNamed(string $name): ProductCategory
{
    return categoryTabLegacyRoot(name: $name);
}

it('links the legacy roots to the current root of the same name and leaves the ambiguous ones (AC-023)', function () {
    $iso = ProductCategory::factory()->create(['name' => 'ISO']);
    $gare = ProductCategory::factory()->create(['name' => 'Gare']);
    $safety = ProductCategory::factory()->create(['name' => 'Sicurezza']);
    ProductCategory::factory()->create(['name' => 'Twin']);
    ProductCategory::factory()->create(['name' => 'twin']);
    $isoLegacy = legacyRootNamed('[OLD] ISO');
    $gareLegacy = legacyRootNamed('[OLD] GARE');
    $gareDashed = legacyRootNamed('[OLD] GARE-');
    $safetyLegacy = legacyRootNamed('[OLD] SICUREZZA 81/08');
    $orphan = legacyRootNamed('[OLD] No match');
    $ambiguous = legacyRootNamed('[OLD] Twin');
    $child = ProductCategory::factory()->childOf($iso)->create(['name' => 'ISO']);
    $child->forceFill(['is_legacy' => true])->save();
    $kept = legacyRootNamed('[OLD] Kept');
    $kept->forceFill(['current_category_id' => $gare->id])->save();
    ProductCategory::factory()->create(['name' => 'Kept']);

    runCurrentCategoryBackfill();

    expect($isoLegacy->fresh()->current_category_id)->toBe($iso->id)
        ->and($gareLegacy->fresh()->current_category_id)->toBe($gare->id)
        ->and($gareDashed->fresh()->current_category_id)->toBe($gare->id)
        ->and($safetyLegacy->fresh()->current_category_id)->toBe($safety->id)
        ->and($orphan->fresh()->current_category_id)->toBeNull()
        ->and($ambiguous->fresh()->current_category_id)->toBeNull()
        ->and($child->fresh()->current_category_id)->toBeNull()
        ->and($kept->fresh()->current_category_id)->toBe($gare->id);
});

it('drops the column on down (AC-023)', function () {
    $migration = currentCategoryMigration();

    $migration->down();
    expect(Schema::hasColumn('product_categories', 'current_category_id'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('product_categories', 'current_category_id'))->toBeTrue();
});
