<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Spec 0320 D-20 / AC-027 (REQUIREMENT CHANGED vs spec 0191): every product has
 * a price and a cost whatever its usages; both default to 0 when absent and
 * are never negative. No "required by usage" rule remains.
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

function pricedProduct(array $usages, float $cost, float $price): Product
{
    $product = Product::factory()->create(['cost' => $cost, 'price' => $price]);
    $product->usages = $usages;
    $product->save();

    return $product;
}

beforeEach(fn () => Sanctum::actingAs(productUserWith(['create', 'update', 'view', 'viewAny'])));

it('AC-027 create: any usage without price and cost stores 0 and 0', function (array $usages) {
    $this->postJson('/api/products', pricingPayload(['usages' => $usages]))
        ->assertCreated()
        ->assertJsonPath('data.price', '0.00')
        ->assertJsonPath('data.cost', '0.00');
})->with([
    'sale' => [['SALE']],
    'cost' => [['COST']],
    'both' => [['SALE', 'COST']],
]);

it('AC-027 create: explicit null counts as 0 and given values are kept', function () {
    $this->postJson('/api/products', pricingPayload(['price' => null, 'cost' => null]))
        ->assertCreated()
        ->assertJsonPath('data.price', '0.00')
        ->assertJsonPath('data.cost', '0.00');

    $this->postJson('/api/products', pricingPayload(['usages' => ['COST'], 'price' => 9, 'cost' => '5.50']))
        ->assertCreated()
        ->assertJsonPath('data.price', '9.00')
        ->assertJsonPath('data.cost', '5.50');
});

it('AC-027 create: a negative price or cost is 422', function () {
    $this->postJson('/api/products', pricingPayload(['price' => -1, 'cost' => -0.5]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['price', 'cost']);
});

it('AC-027 update: absent price and cost stay as they are, even when usages change', function () {
    $product = pricedProduct(['SALE', 'COST'], 40, 100);

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['COST']])->assertOk();
    $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed'])->assertOk();

    expect((float) $product->fresh()->price)->toBe(100.0)
        ->and((float) $product->fresh()->cost)->toBe(40.0);
});

it('AC-027 update: adding a usage to a product with zero cost needs nothing, submitted null resets to 0, negative is 422', function () {
    $product = pricedProduct(['SALE'], 0, 100);

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['SALE', 'COST']])->assertOk();
    $this->patchJson("/api/products/{$product->id}", ['price' => null])->assertOk();
    expect((float) $product->fresh()->price)->toBe(0.0);

    $this->patchJson("/api/products/{$product->id}", ['cost' => -3])
        ->assertStatus(422)->assertJsonValidationErrors(['cost']);
});

it('create: an invalid usages reports its own error without pricing noise', function () {
    $this->postJson('/api/products', pricingPayload(['usages' => ['BOGUS']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['usages.0'])
        ->assertJsonMissingValidationErrors(['price', 'cost']);
});

it('AC-008 grid: a Cost-only product still exposes its stored price', function () {
    pricedProduct(['COST'], 40, 100)->update(['name' => 'CostOnlyGadget']);

    $row = collect($this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->firstWhere('name', 'CostOnlyGadget');

    expect($row)->not->toBeNull()
        ->and((float) $row['price'])->toBe(100.0);
});
