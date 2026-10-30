<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\UserCategoryTabPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// GET/PUT /api/{opportunities|quotes}/category-tab-preferences (spec 0335, AC-005..007).

uses(RefreshDatabase::class);

it('forbids reading and writing without viewAny, writing nothing (AC-005)', function (string $grid) {
    Sanctum::actingAs(categoryTabGridActor($grid, viewAll: false, viewAny: false));

    $this->getJson("/api/{$grid}/category-tab-preferences")->assertForbidden();
    $this->putJson("/api/{$grid}/category-tab-preferences", [
        'favorite_category_ids' => [],
        'show_only_favorites' => false,
    ])->assertForbidden();
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
})->with(['opportunities', 'quotes']);

it('defaults the favourites to the strip categories the competence covers, without writing (AC-006)', function () {
    $actor = categoryTabGridActor('quotes');
    $function = BusinessFunction::factory()->create();
    $covered = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $other = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    EmploymentProfile::factory()->for($actor)->competentIn($function, $covered)->create();
    categoryTabQuote([$covered]);
    categoryTabQuote([$other]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quotes/category-tab-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$covered->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true)
        ->assertJsonPath('data.preferences.is_default', true);
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
});

it('saves per grid, leaves the other modules untouched and rejects unknown ids (AC-007)', function () {
    $actor = categoryTabGridActor('opportunities');
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id, 'module' => 'quotes',
        'favorite_category_ids' => [$b->id], 'show_only_favorites' => true,
    ]);
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id, 'module' => 'request-management',
        'favorite_category_ids' => [$b->id], 'show_only_favorites' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->putJson('/api/opportunities/category-tab-preferences', [
        'favorite_category_ids' => [$a->id],
        'show_only_favorites' => false,
    ])->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$a->id])
        ->assertJsonPath('data.preferences.show_only_favorites', false)
        ->assertJsonPath('data.preferences.is_default', false);

    expect(UserCategoryTabPreference::query()->where('user_id', $actor->id)->where('module', 'opportunities')->count())->toBe(1);
    foreach (['quotes', 'request-management'] as $module) {
        $row = UserCategoryTabPreference::query()->where('user_id', $actor->id)->where('module', $module)->firstOrFail();
        expect($row->favorite_category_ids)->toBe([$b->id])->and($row->show_only_favorites)->toBeTrue();
    }

    $this->putJson('/api/opportunities/category-tab-preferences', [
        'favorite_category_ids' => [999999],
        'show_only_favorites' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('favorite_category_ids.0');
});
