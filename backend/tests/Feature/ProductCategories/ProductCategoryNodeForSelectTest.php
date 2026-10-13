<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0223 D-5 — the node picker of the commission configurator: every ACTIVE
// category, containers and leaves alike, `is_selectable` ignored; inactive
// ones only through the `ids[]` hydration of a saved value.
uses(RefreshDatabase::class);

it('requires authentication (401, AC-007)', function () {
    $this->getJson('/api/product-category-nodes/for-select')->assertUnauthorized();
});

it('lists containers and leaves alike, never the inactive ones (AC-007)', function () {
    $container = ProductCategory::factory()->create(['name' => 'ISO', 'is_selectable' => false]);
    $leaf = ProductCategory::factory()->create(['name' => 'ISO 9001', 'parent_id' => $container->id]);
    $inactive = ProductCategory::factory()->create(['name' => 'Retired', 'is_active' => false]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-category-nodes/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($container->id, $leaf->id)
        ->and($ids)->not->toContain($inactive->id)
        ->and($response->json('items.0'))->toBe(['id' => $container->id, 'label' => 'ISO']);
});

it('searches by name and paginates (AC-007)', function () {
    ProductCategory::factory()->create(['name' => 'GDPR', 'is_selectable' => false]);
    ProductCategory::factory()->create(['name' => 'GDPR_Dpo']);
    ProductCategory::factory()->create(['name' => 'SOA-']);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-category-nodes/for-select?search=GDPR&limit=1&offset=1')->assertOk();

    expect($response->json('pagination.total'))->toBe(2)
        ->and(collect($response->json('items'))->pluck('label')->all())->toBe(['GDPR_Dpo']);
});

it('hydrates a saved inactive category requested through ids[] (AC-007)', function () {
    $inactive = ProductCategory::factory()->create(['name' => 'Retired', 'is_active' => false]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/product-category-nodes/for-select?ids[]='.$inactive->id)->assertOk();

    expect(collect($response->json('items'))->pluck('id'))->toContain($inactive->id);
});
