<?php

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0374, D-9: the current root a legacy root continues (AC-021).

uses(RefreshDatabase::class);

function currentCategoryPatch(ProductCategory $category, mixed $currentId): TestResponse
{
    return test()->patchJson("/api/product-categories/{$category->id}", ['current_category_id' => $currentId]);
}

it('links a legacy root to a current root and logs it (AC-021)', function () {
    $current = ProductCategory::factory()->create(['name' => 'ISO']);
    $legacy = categoryTabLegacyRoot(name: '[OLD] ISO');
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    currentCategoryPatch($legacy, $current->id)->assertOk()
        ->assertJsonPath('data.current_category_id', $current->id)
        ->assertJsonPath('data.current_category.name', 'ISO')
        ->assertJsonPath('data.is_legacy', true);

    expect($legacy->fresh()->current_category_id)->toBe($current->id)
        ->and($legacy->activities()->count())->toBeGreaterThan(0);
    $this->getJson("/api/product-categories/{$current->id}")->assertOk()->assertJsonPath('data.is_legacy', false)->assertJsonPath('data.current_category', null);

    currentCategoryPatch($legacy, null)->assertOk()->assertJsonPath('data.current_category', null);
    expect($legacy->fresh()->current_category_id)->toBeNull();
});

it('refuses a link on a category that is not a legacy root or to an invalid target (AC-021)', function () {
    $current = ProductCategory::factory()->create();
    $legacy = categoryTabLegacyRoot();
    $legacyChild = ProductCategory::factory()->childOf($legacy)->create();
    $legacyChild->forceFill(['is_legacy' => true])->save();
    $notLegacy = ProductCategory::factory()->create();
    $subcategory = ProductCategory::factory()->childOf($current)->create();
    $otherLegacy = categoryTabLegacyRoot();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    currentCategoryPatch($notLegacy, $current->id)->assertUnprocessable()->assertJsonValidationErrors('current_category_id');
    currentCategoryPatch($legacyChild, $current->id)->assertUnprocessable()->assertJsonValidationErrors('current_category_id');
    currentCategoryPatch($legacy, $otherLegacy->id)->assertUnprocessable()->assertJsonValidationErrors('current_category_id');
    currentCategoryPatch($legacy, $subcategory->id)->assertUnprocessable()->assertJsonValidationErrors('current_category_id');
    currentCategoryPatch($legacy, 999999)->assertUnprocessable()->assertJsonValidationErrors('current_category_id');

    expect($legacy->fresh()->current_category_id)->toBeNull();
});

it('refuses the field on create and without the field permission (AC-021)', function () {
    $current = ProductCategory::factory()->create();
    $legacy = categoryTabLegacyRoot();
    Sanctum::actingAs(productCategoryUserWith(['view', 'create']));

    $this->postJson('/api/product-categories', ['name' => 'New', 'current_category_id' => $current->id])->assertUnprocessable();

    Sanctum::actingAs(productCategoryUserWith(['view']));
    currentCategoryPatch($legacy, $current->id)->assertForbidden();
    expect($legacy->fresh()->current_category_id)->toBeNull();
});

it('clears the link when the current category is deleted (AC-021)', function () {
    $current = ProductCategory::factory()->create();
    $legacy = categoryTabLegacyRoot($current);

    $current->delete();

    expect($legacy->fresh()->current_category_id)->toBeNull();
});

it('lists the roots without the legacy ones and without "Tutte" with current_only (AC-021)', function () {
    $current = ProductCategory::factory()->create(['name' => 'Current root']);
    $legacy = categoryTabLegacyRoot(name: '[OLD] Legacy root');
    ProductCategory::factory()->childOf($current)->create(['name' => 'A child']);
    Sanctum::actingAs(productCategoryUserWith([]));

    $all = collect($this->getJson('/api/product-category-roots/for-select')->assertOk()->json('items'));
    $only = collect($this->getJson('/api/product-category-roots/for-select?current_only=1')->assertOk()->json('items'));

    expect($all->pluck('id')->all())->toContain(0, $legacy->id, $current->id)
        ->and($only->pluck('id')->all())->toBe([$current->id]);
});
