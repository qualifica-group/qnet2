<?php

use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Spec 0320, D-4/D-18: the products grid `cost` and `price` columns show the
 * effective value — the default supplier's once the product has suppliers,
 * else the standard one — and sort and filter on that same value.
 */
function effectivePricingProducts(): void
{
    $multi = Product::factory()->create(['name' => 'Multi', 'cost' => 16, 'price' => 160]);
    ProductSupplier::factory()->default()->create(['product_id' => $multi->id, 'cost' => 15, 'price' => 150]);
    ProductSupplier::factory()->create(['product_id' => $multi->id, 'cost' => 13, 'price' => 130]);

    Product::factory()->create(['name' => 'Standard', 'cost' => 9, 'price' => 90]);
    Product::factory()->create(['name' => 'Empty', 'cost' => 0, 'price' => 0]);
}

function effectivePricingRows(array $body = []): Collection
{
    return collect(test()->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25, ...$body])->assertOk()->json('items'));
}

it('0320: the cost column shows the default supplier cost, else the standard cost', function () {
    effectivePricingProducts();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $costs = effectivePricingRows()->pluck('cost', 'name');

    expect($costs['Multi'])->toEqual(15)
        ->and($costs['Standard'])->toEqual(9)
        ->and($costs['Empty'])->toEqual(0);
});

it('0320: the cost column sorts on the effective cost', function () {
    effectivePricingProducts();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $names = effectivePricingRows(['sortModel' => [['colId' => 'cost', 'sort' => 'desc']]])
        ->pluck('name')->all();

    expect($names)->toBe(['Multi', 'Standard', 'Empty']);
});

it('0320: the cost column filters on the effective cost, never the hidden standard cost', function () {
    effectivePricingProducts();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $equals = effectivePricingRows(['filterModel' => ['cost' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 15]]]);
    $hidden = effectivePricingRows(['filterModel' => ['cost' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 16]]]);
    $range = effectivePricingRows(['filterModel' => ['cost' => ['filterType' => 'number', 'type' => 'inRange', 'filter' => 8, 'filterTo' => 10]]]);

    expect($equals->pluck('name')->all())->toBe(['Multi'])
        ->and($hidden->all())->toBe([])
        ->and($range->pluck('name')->all())->toBe(['Standard']);
});

it('AC-023: the price column shows the default supplier price, else the standard price', function () {
    effectivePricingProducts();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $prices = effectivePricingRows()->pluck('price', 'name');

    expect($prices['Multi'])->toEqual(150)
        ->and($prices['Standard'])->toEqual(90)
        ->and($prices['Empty'])->toEqual(0);
});

it('AC-023: the price column sorts on the effective price', function () {
    effectivePricingProducts();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $names = effectivePricingRows(['sortModel' => [['colId' => 'price', 'sort' => 'asc']]])
        ->pluck('name')->all();

    expect($names)->toBe(['Empty', 'Standard', 'Multi']);
});

it('AC-023: the price column filters on the effective price, never the hidden standard price', function () {
    effectivePricingProducts();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $equals = effectivePricingRows(['filterModel' => ['price' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 150]]]);
    $hidden = effectivePricingRows(['filterModel' => ['price' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 160]]]);
    $range = effectivePricingRows(['filterModel' => ['price' => ['filterType' => 'number', 'type' => 'inRange', 'filter' => 80, 'filterTo' => 100]]]);

    expect($equals->pluck('name')->all())->toBe(['Multi'])
        ->and($hidden->all())->toBe([])
        ->and($range->pluck('name')->all())->toBe(['Standard']);
});
