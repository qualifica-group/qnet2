<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Spec 0191: price/cost are required per usage (Sellable -> price, Usable as
 * cost -> cost), evaluated on effective values.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pricingPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Widget',
        'category_id' => ProductCategory::factory()->create()->id,
        'product_type' => 'SERVICE',
    ], $overrides);
}

function pricedProduct(array $usages, ?float $cost, ?float $price): Product
{
    $product = Product::factory()->create(['cost' => $cost, 'price' => $price]);
    $product->usages = $usages;
    $product->save();

    return $product;
}

beforeEach(fn () => Sanctum::actingAs(productUserWith(['create', 'update', 'view', 'viewAny'])));

it('AC-001 create Sale only: cost optional (null), price required', function () {
    $this->postJson('/api/products', pricingPayload(['usages' => ['SALE'], 'price' => 50]))
        ->assertCreated()
        ->assertJsonPath('data.cost', null);

    $this->postJson('/api/products', pricingPayload(['usages' => ['SALE'], 'cost' => 5]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['price'])
        ->assertJsonMissingValidationErrors(['cost']);
});

it('AC-002 create Cost only: price optional (null), cost required', function () {
    $this->postJson('/api/products', pricingPayload(['usages' => ['COST'], 'cost' => 30]))
        ->assertCreated()
        ->assertJsonPath('data.price', null);

    $this->postJson('/api/products', pricingPayload(['usages' => ['COST'], 'price' => 5]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cost'])
        ->assertJsonMissingValidationErrors(['price']);
});

it('AC-003 create Sale+Cost needs both; no usages needs price only', function () {
    $this->postJson('/api/products', pricingPayload(['usages' => ['SALE', 'COST'], 'price' => 5]))
        ->assertStatus(422)->assertJsonValidationErrors(['cost']);
    $this->postJson('/api/products', pricingPayload(['usages' => ['SALE', 'COST'], 'cost' => 5]))
        ->assertStatus(422)->assertJsonValidationErrors(['price']);
    $this->postJson('/api/products', pricingPayload(['usages' => ['SALE', 'COST'], 'cost' => 5, 'price' => 9]))
        ->assertCreated();

    $this->postJson('/api/products', pricingPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['price'])
        ->assertJsonMissingValidationErrors(['cost']);
    $this->postJson('/api/products', pricingPayload(['price' => 9]))->assertCreated();
});

it('create: an explicit null price on a Sellable product is rejected', function () {
    $this->postJson('/api/products', pricingPayload(['price' => null]))
        ->assertStatus(422)->assertJsonValidationErrors(['price']);
});

it('create: an invalid usages reports its own error without pricing noise', function () {
    $this->postJson('/api/products', pricingPayload(['usages' => ['BOGUS']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['usages.0'])
        ->assertJsonMissingValidationErrors(['price']);
});

it('AC-004 update: dropping Sale keeps the stored price', function () {
    $product = pricedProduct(['SALE', 'COST'], 40, 100);

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['COST']])->assertOk();

    expect((float) $product->fresh()->price)->toBe(100.0);
});

it('AC-005 update: adding Cost to a product with null cost needs a cost', function () {
    $product = pricedProduct(['SALE'], null, 100);

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['SALE', 'COST']])
        ->assertStatus(422)->assertJsonValidationErrors(['cost']);

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['SALE', 'COST'], 'cost' => 60])
        ->assertOk();
    expect((float) $product->fresh()->cost)->toBe(60.0);
});

it('AC-006 update: null price is rejected on Sale, accepted on Cost only', function () {
    $sale = pricedProduct(['SALE'], null, 100);
    $this->patchJson("/api/products/{$sale->id}", ['price' => null])
        ->assertStatus(422)->assertJsonValidationErrors(['price']);

    $costOnly = pricedProduct(['COST'], 40, 100);
    $this->patchJson("/api/products/{$costOnly->id}", ['price' => null])->assertOk();
    expect($costOnly->fresh()->price)->toBeNull();
});

it('update: an unrelated partial PATCH on a fully priced product passes', function () {
    $product = pricedProduct(['SALE'], 10, 20);

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed'])->assertOk();
});

it('AC-008 grid: a Cost-only product still exposes its stored price', function () {
    pricedProduct(['COST'], 40, 100)->update(['name' => 'CostOnlyGadget']);

    $row = collect($this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->firstWhere('name', 'CostOnlyGadget');

    expect($row)->not->toBeNull()
        ->and((float) $row['price'])->toBe(100.0);
});
