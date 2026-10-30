<?php

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Row scope by productCategoryId on the opportunities and quotes grids (spec 0335, AC-008..012).

uses(RefreshDatabase::class);

const CATEGORY_SCOPE_PAGE = ['startRow' => 0, 'endRow' => 25];

it('scopes opportunity rows to the category, once each, matching the strip count (AC-008)', function () {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $twoLines = categoryTabOpportunity($a, $a);
    $mixed = categoryTabOpportunity($a, $b);
    categoryTabOpportunity($b);
    Sanctum::actingAs(categoryTabGridActor('opportunities'));

    $response = $this->postJson('/api/tables/opportunities/rows', [...CATEGORY_SCOPE_PAGE, 'productCategoryId' => $a->id])->assertOk();
    $strip = $this->getJson('/api/opportunities/product-categories')->json('data.categories');

    expect(collect($response->json('items'))->pluck('id')->sort()->values()->all())->toBe([$twoLines->id, $mixed->id])
        ->and($response->json('pagination.total'))->toBe(collect($strip)->firstWhere('id', $a->id)['records_count']);

    $this->postJson('/api/tables/opportunities/rows', CATEGORY_SCOPE_PAGE)
        ->assertOk()
        ->assertJsonPath('pagination.total', 3);
});

it('scopes quote rows to the revenue-line category, once each (AC-009)', function () {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    $match = categoryTabQuote([$a, $a]);
    categoryTabQuote([$b]);
    categoryTabQuote([$a], cost: true);
    categoryTabQuote([]);
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $response = $this->postJson('/api/tables/quotes/rows', [...CATEGORY_SCOPE_PAGE, 'productCategoryId' => $a->id])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$match->id])
        ->and($response->json('pagination.total'))->toBe(1);
});

it('combines the category with the registry and opportunity scopes (AC-010)', function () {
    $category = ProductCategory::factory()->create();
    $registry = Registry::factory()->create();
    $inScope = categoryTabOpportunity($category);
    $inScope->update(['registry_id' => $registry->id]);
    categoryTabOpportunity($category);
    Opportunity::factory()->create(['registry_id' => $registry->id]);
    Sanctum::actingAs(categoryTabGridActor('opportunities'));

    $rows = fn (array $extra) => $this->postJson('/api/tables/opportunities/rows', [...CATEGORY_SCOPE_PAGE, ...$extra])->assertOk();

    expect(collect($rows(['productCategoryId' => $category->id, 'registryId' => $registry->id])->json('items'))->pluck('id')->all())->toBe([$inScope->id])
        ->and($rows(['registryId' => $registry->id])->json('pagination.total'))->toBe(2);

    $quoteHit = categoryTabQuote([$category], opportunity: $inScope);
    categoryTabQuote([$category]);
    categoryTabQuote([$category], opportunity: Opportunity::factory()->create(['registry_id' => $registry->id]));
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $quotes = $this->postJson('/api/tables/quotes/rows', [
        ...CATEGORY_SCOPE_PAGE, 'productCategoryId' => $category->id, 'opportunityId' => $inScope->id,
    ])->assertOk();

    expect(collect($quotes->json('items'))->pluck('id')->all())->toBe([$quoteHit->id]);

    $both = $this->postJson('/api/tables/quotes/rows', [
        ...CATEGORY_SCOPE_PAGE, 'productCategoryId' => $category->id, 'registryId' => $registry->id,
    ])->assertOk();

    expect($both->json('pagination.total'))->toBe(2);
});

it('computes distinct values only on the category rows (AC-011)', function (string $grid) {
    [$a, $b] = ProductCategory::factory()->count(2)->create();
    if ($grid === 'opportunities') {
        $inside = categoryTabOpportunity($a);
        categoryTabOpportunity($b);
        $column = 'name';
    } else {
        $inside = categoryTabQuote([$a]);
        categoryTabQuote([$b]);
        $column = 'code';
    }
    Sanctum::actingAs(categoryTabGridActor($grid));

    $this->postJson("/api/tables/{$grid}/values", ['columnId' => $column, 'productCategoryId' => $a->id])
        ->assertOk()
        ->assertJsonPath('data.values', [$inside->{$column}]);
    expect($this->postJson("/api/tables/{$grid}/values", ['columnId' => $column])->json('data.values'))->toHaveCount(2);
})->with(['opportunities', 'quotes']);

it('keeps the same columns with or without a category (AC-012)', function (string $grid) {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(categoryTabGridActor($grid));

    $plain = $this->getJson("/api/tables/{$grid}/columns")->assertOk()->json();
    $scoped = $this->getJson("/api/tables/{$grid}/columns?product_category_id={$category->id}")->assertOk()->json();

    expect($scoped)->toBe($plain);
})->with(['opportunities', 'quotes']);
