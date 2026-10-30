<?php

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// GET /api/{opportunities|quotes}/product-categories (spec 0335, AC-001..005).

uses(RefreshDatabase::class);

it('counts distinct opportunities per category, ordered by name, without zero categories (AC-001)', function () {
    $b = ProductCategory::factory()->create(['name' => 'B cat']);
    $a = ProductCategory::factory()->create(['name' => 'A cat']);
    ProductCategory::factory()->create(['name' => 'Empty']);
    categoryTabOpportunity($a, $a);
    categoryTabOpportunity($a);
    categoryTabOpportunity($b);
    Sanctum::actingAs(categoryTabGridActor('opportunities'));

    $this->getJson('/api/opportunities/product-categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertExactJson([
            'success' => true,
            'message' => 'OK',
            'data' => ['categories' => [
                ['id' => $a->id, 'name' => 'A cat', 'records_count' => 2],
                ['id' => $b->id, 'name' => 'B cat', 'records_count' => 1],
            ]],
        ]);
});

it('excludes opportunities the actor cannot see (AC-002)', function () {
    $visible = ProductCategory::factory()->create();
    $hidden = ProductCategory::factory()->create();
    $actor = categoryTabGridActor('opportunities', viewAll: false);
    $mine = categoryTabOpportunity($visible);
    $mine->update(['supervisor_id' => $actor->id]);
    categoryTabOpportunity($hidden);
    categoryTabOpportunity($visible);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/opportunities/product-categories')->assertOk();

    expect($response->json('data.categories'))->toBe([
        ['id' => $visible->id, 'name' => $visible->name, 'records_count' => 1],
    ]);
});

it('counts quotes by the categories of their revenue lines, once per quote, with no fallback (AC-003)', function () {
    $a = ProductCategory::factory()->create();
    $b = ProductCategory::factory()->create();
    categoryTabQuote([$a, $a]);
    categoryTabQuote([$b], cost: true);
    categoryTabQuote([]);
    // The Opportunity carries lines on B: the quote must NOT inherit them (D-1).
    categoryTabQuote([], opportunity: categoryTabOpportunity($b));
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $response = $this->getJson('/api/quotes/product-categories')->assertOk();

    expect($response->json('data.categories'))->toBe([
        ['id' => $a->id, 'name' => $a->name, 'records_count' => 1],
    ]);
});

it('excludes quotes the actor cannot see (AC-004)', function () {
    $category = ProductCategory::factory()->create();
    $actor = categoryTabGridActor('quotes', viewAll: false);
    categoryTabQuote([$category]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quotes/product-categories')
        ->assertOk()
        ->assertJsonPath('data.categories', []);
});

it('forbids a user without viewAny and requires authentication (AC-005)', function (string $grid) {
    $this->getJson("/api/{$grid}/product-categories")->assertUnauthorized();

    Sanctum::actingAs(categoryTabGridActor($grid, viewAll: false, viewAny: false));

    $this->getJson("/api/{$grid}/product-categories")->assertForbidden();
})->with(['opportunities', 'quotes']);
