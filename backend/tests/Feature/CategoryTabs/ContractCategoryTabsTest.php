<?php

use App\Models\ProductCategory;
use App\Models\UserCategoryTabPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Category tab strip, preferences and row scope on the contracts grid (spec 0335 rev.2, D-8, AC-020..022).

uses(RefreshDatabase::class);

it('counts distinct contracts by the revenue categories of their quote, only the visible ones (AC-020)', function () {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $actor = categoryTabGridActor('contracts', viewAll: false);
    categoryTabContract([$a, $a])->quote->update(['supervisor_id' => $actor->id]);
    categoryTabContract([$b], cost: true)->quote->update(['supervisor_id' => $actor->id]);
    categoryTabContract([$a]);
    Sanctum::actingAs($actor);

    $visible = $this->getJson('/api/contracts/product-categories')->assertOk()->json('data.categories');

    expect($visible)->toBe([['id' => $a->id, 'name' => $a->name, 'records_count' => 1]]);

    Sanctum::actingAs(categoryTabGridActor('contracts'));
    $all = $this->getJson('/api/contracts/product-categories')->assertOk()->json('data.categories');

    expect($all)->toBe([['id' => $a->id, 'name' => $a->name, 'records_count' => 2]]);

    Sanctum::actingAs(categoryTabGridActor('contracts', viewAny: false));
    $this->getJson('/api/contracts/product-categories')->assertForbidden();
});

it('reads and saves the preferences on its own module, forbidden without viewAny (AC-021)', function () {
    $actor = categoryTabGridActor('contracts');
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    categoryTabContract([$a]);
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id, 'module' => 'quotes',
        'favorite_category_ids' => [$b->id], 'show_only_favorites' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contracts/category-tab-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.is_default', true);

    $this->putJson('/api/contracts/category-tab-preferences', [
        'favorite_category_ids' => [$a->id],
        'show_only_favorites' => false,
    ])->assertOk()->assertJsonPath('data.preferences.favorite_category_ids', [$a->id]);

    expect(UserCategoryTabPreference::query()->where('module', 'contracts')->first()->favorite_category_ids)->toBe([$a->id])
        ->and(UserCategoryTabPreference::query()->where('module', 'quotes')->first()->favorite_category_ids)->toBe([$b->id]);

    Sanctum::actingAs(categoryTabGridActor('contracts', viewAny: false));
    $this->getJson('/api/contracts/category-tab-preferences')->assertForbidden();
    $this->putJson('/api/contracts/category-tab-preferences', [
        'favorite_category_ids' => [],
        'show_only_favorites' => false,
    ])->assertForbidden();
});

it('scopes rows and distinct values to the category, once each, with the columns unchanged (AC-022)', function () {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $match = categoryTabContract([$a, $a]);
    categoryTabContract([$b]);
    categoryTabContract([$a], cost: true);
    Sanctum::actingAs(categoryTabGridActor('contracts'));

    $page = ['startRow' => 0, 'endRow' => 25];
    $rows = $this->postJson('/api/tables/contracts/rows', [...$page, 'productCategoryId' => $a->id])->assertOk();
    $strip = $this->getJson('/api/contracts/product-categories')->json('data.categories');

    expect(collect($rows->json('items'))->pluck('id')->all())->toBe([$match->id])
        ->and($rows->json('pagination.total'))->toBe(collect($strip)->firstWhere('id', $a->id)['records_count'])
        ->and($this->postJson('/api/tables/contracts/rows', $page)->json('pagination.total'))->toBe(3);

    $values = fn (array $extra) => $this->postJson('/api/tables/contracts/values', ['columnId' => 'registry', ...$extra])->assertOk()->json('data.values');
    expect($values(['productCategoryId' => $a->id]))->toHaveCount(1)
        ->and($values([]))->toHaveCount(3);

    $plain = $this->getJson('/api/tables/contracts/columns')->assertOk()->json();
    $scoped = $this->getJson("/api/tables/contracts/columns?product_category_id={$a->id}")->assertOk()->json();
    expect($scoped)->toBe($plain);
});
