<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\UserCategoryTabPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// GET|PUT /api/{module}/category-tab-preferences?level=root (spec 0337, AC-007).

uses(RefreshDatabase::class);

it('proposes the roots whose branch holds a covered category of the strip, without writing (AC-007)', function (string $module) {
    $actor = categoryTabGridActor($module);
    $function = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    $uncoveredRoot = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    EmploymentProfile::factory()->for($actor)->competentIn($function, $child)->create();
    categoryTabRecord($module, [$child]);
    categoryTabRecord($module, [$uncoveredRoot]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/{$module}/category-tab-preferences?level=root")
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$root->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true)
        ->assertJsonPath('data.preferences.is_default', true);
    $this->getJson("/api/{$module}/category-tab-preferences")
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$child->id]);
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
})->with(categoryTabModules());

it('writes the "{module}:root" row without touching the all-level row (AC-007)', function (string $module) {
    $actor = categoryTabGridActor($module);
    [$root, $child] = categoryTabTree();
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id, 'module' => $module,
        'favorite_category_ids' => [$child->id], 'show_only_favorites' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/{$module}/category-tab-preferences?level=root", [
        'favorite_category_ids' => [$root->id],
        'show_only_favorites' => false,
    ])->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$root->id])
        ->assertJsonPath('data.preferences.is_default', false);

    $this->getJson("/api/{$module}/category-tab-preferences?level=root")
        ->assertJsonPath('data.preferences.favorite_category_ids', [$root->id]);
    $this->getJson("/api/{$module}/category-tab-preferences")
        ->assertJsonPath('data.preferences.favorite_category_ids', [$child->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true);
    expect(UserCategoryTabPreference::query()->where('user_id', $actor->id)->pluck('module')->sort()->values()->all())
        ->toBe([$module, "{$module}:root"]);
})->with(categoryTabModules());

it('rejects an unknown level and requires viewAny on the root level (AC-004, AC-007)', function (string $module) {
    Sanctum::actingAs(categoryTabGridActor($module));
    $this->getJson("/api/{$module}/category-tab-preferences?level=foo")->assertUnprocessable();
    $this->putJson("/api/{$module}/category-tab-preferences?level=foo", ['favorite_category_ids' => [], 'show_only_favorites' => false])->assertUnprocessable();

    Sanctum::actingAs(categoryTabGridActor($module, viewAll: false, viewAny: false));
    $this->getJson("/api/{$module}/category-tab-preferences?level=root")->assertForbidden();
    $this->putJson("/api/{$module}/category-tab-preferences?level=root", ['favorite_category_ids' => [], 'show_only_favorites' => false])->assertForbidden();
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
})->with(categoryTabModules());
