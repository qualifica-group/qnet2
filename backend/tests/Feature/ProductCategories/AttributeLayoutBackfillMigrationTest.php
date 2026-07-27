<?php

declare(strict_types=1);

use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0062 D3 revised: the backfill that turns a category's LONE per-context
// row into the shared `all` layout, preserving the rendering it already had
// under the removed implicit create -> edit -> view fallback.

uses(RefreshDatabase::class);

/** The backfill migration instance (already replayed empty by RefreshDatabase). */
function attributeLayoutBackfill(): Migration
{
    return require database_path('migrations/2026_07_27_140000_backfill_attribute_layout_all_scope.php');
}

it('promotes a lone per-context row to the shared scope', function () {
    $category = ProductCategory::factory()->create();
    $layout = AttributeLayout::factory()->for($category, 'productCategory')
        ->create(['context' => 'product', 'form_mode' => 'create']);

    attributeLayoutBackfill()->up();

    expect($layout->fresh()->form_mode)->toBe(LayoutFormScope::All);
});

it('leaves a context that already had several per-mode rows untouched', function () {
    $category = ProductCategory::factory()->create();
    foreach (['create', 'view'] as $scope) {
        AttributeLayout::factory()->for($category, 'productCategory')
            ->create(['context' => 'product', 'form_mode' => $scope]);
    }

    attributeLayoutBackfill()->up();

    expect(AttributeLayout::query()->pluck('form_mode')->all())
        ->toEqualCanonicalizing([LayoutFormScope::Create, LayoutFormScope::View]);
});

it('counts contexts separately: one row per context is one shared layout each', function () {
    $category = ProductCategory::factory()->create();
    foreach (['product', 'opportunity'] as $context) {
        AttributeLayout::factory()->for($category, 'productCategory')
            ->create(['context' => $context, 'form_mode' => 'create']);
    }

    attributeLayoutBackfill()->up();

    expect(AttributeLayout::query()->pluck('form_mode')->all())
        ->toBe([LayoutFormScope::All, LayoutFormScope::All]);
});

it('reverses a shared row back to `create`, or drops it when a create row already exists', function () {
    $promoted = ProductCategory::factory()->create();
    AttributeLayout::factory()->for($promoted, 'productCategory')
        ->create(['context' => 'product', 'form_mode' => 'all']);

    $conflicting = ProductCategory::factory()->create();
    foreach (['all', 'create'] as $scope) {
        AttributeLayout::factory()->for($conflicting, 'productCategory')
            ->create(['context' => 'product', 'form_mode' => $scope]);
    }

    attributeLayoutBackfill()->down();

    expect(AttributeLayout::query()->where('product_category_id', $promoted->id)->pluck('form_mode')->all())
        ->toBe([LayoutFormScope::Create])
        ->and(AttributeLayout::query()->where('product_category_id', $conflicting->id)->pluck('form_mode')->all())
        ->toBe([LayoutFormScope::Create]);
});
