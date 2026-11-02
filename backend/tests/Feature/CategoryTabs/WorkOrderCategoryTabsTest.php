<?php

use App\Models\ProductCategory;
use App\Models\UserCategoryTabPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Category tab strip, preferences and row scope on the work orders grid (spec 0335 rev.3, D-9, AC-024..026).

uses(RefreshDatabase::class);

it('counts distinct work orders by the revenue categories of their source offers, only the visible ones (AC-024)', function () {
    [$a, $b, $c] = ProductCategory::factory()->count(3)->create();
    $actor = categoryTabGridActor('work-orders', viewAll: false);
    categoryTabWorkOrder([$a, $a])->supervisors()->attach($actor->id);
    categoryTabMultiWorkOrder(categoryTabQuote([$a]), categoryTabQuote([$b]))->supervisors()->attach($actor->id);
    categoryTabWorkOrder([$c], cost: true)->supervisors()->attach($actor->id);
    categoryTabWorkOrder([$b]);
    Sanctum::actingAs($actor);

    $visible = collect($this->getJson('/api/work-orders/product-categories')->assertOk()->json('data.categories'))->pluck('records_count', 'id')->all();

    expect($visible)->toBe([$a->id => 2, $b->id => 1]);

    Sanctum::actingAs(categoryTabGridActor('work-orders'));
    $all = collect($this->getJson('/api/work-orders/product-categories')->assertOk()->json('data.categories'))->pluck('records_count', 'id')->all();

    expect($all)->toBe([$a->id => 2, $b->id => 2]);

    Sanctum::actingAs(categoryTabGridActor('work-orders', viewAny: false));
    $this->getJson('/api/work-orders/product-categories')->assertForbidden();
});

it('reads and saves the preferences on its own module, forbidden without viewAny (AC-025)', function () {
    $actor = categoryTabGridActor('work-orders');
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    categoryTabWorkOrder([$a]);
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id, 'module' => 'contracts',
        'favorite_category_ids' => [$b->id], 'show_only_favorites' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/work-orders/category-tab-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.is_default', true);

    $this->putJson('/api/work-orders/category-tab-preferences', [
        'favorite_category_ids' => [$a->id],
        'show_only_favorites' => false,
    ])->assertOk()->assertJsonPath('data.preferences.favorite_category_ids', [$a->id]);

    expect(UserCategoryTabPreference::query()->where('module', 'work-orders')->first()->favorite_category_ids)->toBe([$a->id])
        ->and(UserCategoryTabPreference::query()->where('module', 'contracts')->first()->favorite_category_ids)->toBe([$b->id]);

    Sanctum::actingAs(categoryTabGridActor('work-orders', viewAny: false));
    $this->getJson('/api/work-orders/category-tab-preferences')->assertForbidden();
    $this->putJson('/api/work-orders/category-tab-preferences', [
        'favorite_category_ids' => [],
        'show_only_favorites' => false,
    ])->assertForbidden();
});

it('scopes rows and distinct values to the category, once each, with the columns unchanged (AC-026)', function () {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $mono = categoryTabWorkOrder([$a, $a]);
    $multi = categoryTabMultiWorkOrder(categoryTabQuote([$b]), categoryTabQuote([$a]));
    categoryTabWorkOrder([$b]);
    categoryTabWorkOrder([$a], cost: true);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $page = ['startRow' => 0, 'endRow' => 25];
    $rows = $this->postJson('/api/tables/work-orders/rows', [...$page, 'productCategoryId' => $a->id])->assertOk();
    $strip = $this->getJson('/api/work-orders/product-categories')->json('data.categories');

    expect(collect($rows->json('items'))->pluck('id')->sort()->values()->all())->toBe([$mono->id, $multi->id])
        ->and($rows->json('pagination.total'))->toBe(collect($strip)->firstWhere('id', $a->id)['records_count'])
        ->and($this->postJson('/api/tables/work-orders/rows', $page)->json('pagination.total'))->toBe(4);

    $values = fn (array $extra) => $this->postJson('/api/tables/work-orders/values', ['columnId' => 'code', ...$extra])->assertOk()->json('data.values');
    expect($values(['productCategoryId' => $a->id]))->toHaveCount(2)
        ->and($values([]))->toHaveCount(4);

    $plain = $this->getJson('/api/tables/work-orders/columns')->assertOk()->json();
    $scoped = $this->getJson("/api/tables/work-orders/columns?product_category_id={$a->id}")->assertOk()->json();
    expect($scoped)->toBe($plain);
});

it('combines the category scope with the quote scope of the contract tab (AC-026)', function () {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $match = categoryTabWorkOrder([$a]);
    categoryTabWorkOrder([$a]);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $rows = fn (int $categoryId) => $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryId, 'quoteId' => $match->quote_id,
    ])->assertOk()->json('items');

    expect(collect($rows($a->id))->pluck('id')->all())->toBe([$match->id])
        ->and($rows($b->id))->toBe([]);
});
