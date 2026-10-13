<?php

use App\Models\CostType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/**
 * Default cost type of a product (spec 0251): PATCH, detail, mass action,
 * for-select meta and grid column.
 */
uses(RefreshDatabase::class);

it('AC-002: PATCH sets a valid cost type and the detail exposes it', function () {
    $costType = CostType::factory()->create(['name' => 'Rent', 'color' => 'blue']);
    $product = Product::factory()->create();
    Sanctum::actingAs(productUserWith(['view', 'update']));

    $this->patchJson("/api/products/{$product->id}", ['cost_type_id' => $costType->id])
        ->assertOk()
        ->assertJsonPath('data.cost_type_id', $costType->id)
        ->assertJsonPath('data.cost_type', ['id' => $costType->id, 'name' => 'Rent', 'color' => 'blue']);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.cost_type.id', $costType->id);
});

it('AC-002: PATCH rejects an unknown cost type and accepts null to clear it', function () {
    $costType = CostType::factory()->create();
    $product = Product::factory()->create(['cost_type_id' => $costType->id]);
    Sanctum::actingAs(productUserWith(['view', 'update']));

    $this->patchJson("/api/products/{$product->id}", ['cost_type_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cost_type_id');

    $this->patchJson("/api/products/{$product->id}", ['cost_type_id' => null])
        ->assertOk()
        ->assertJsonPath('data.cost_type', null);

    expect($product->fresh()->cost_type_id)->toBeNull();
});

it('AC-002: PATCH rejects an inactive cost type unless it is the one already assigned', function () {
    $assigned = CostType::factory()->create(['is_active' => false]);
    $other = CostType::factory()->create(['is_active' => false]);
    $product = Product::factory()->create(['cost_type_id' => $assigned->id]);
    Sanctum::actingAs(productUserWith(['view', 'update']));

    $this->patchJson("/api/products/{$product->id}", ['cost_type_id' => $assigned->id])->assertOk();
    $this->patchJson("/api/products/{$product->id}", ['cost_type_id' => $other->id])->assertUnprocessable();
});

it('AC-003: the mass action sets and clears the cost type on every product', function () {
    $costType = CostType::factory()->create();
    $ids = Product::factory()->count(3)->create()->pluck('id')->all();
    Sanctum::actingAs(productUserWith(['update']));

    $this->postJson('/api/products/cost-type', ['product_ids' => $ids, 'cost_type_id' => $costType->id])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.updated', 3);

    expect(Product::query()->whereIn('id', $ids)->where('cost_type_id', $costType->id)->count())->toBe(3)
        ->and(Activity::query()->where('subject_type', (new Product)->getMorphClass())->whereIn('subject_id', $ids)->where('event', 'updated')->count())->toBe(3);

    $this->postJson('/api/products/cost-type', ['product_ids' => $ids, 'cost_type_id' => null])
        ->assertOk()
        ->assertJsonPath('data.updated', 3);

    expect(Product::query()->whereIn('id', $ids)->whereNotNull('cost_type_id')->count())->toBe(0);
});

it('AC-003: the mass action is forbidden without products.update', function () {
    $product = Product::factory()->create();
    Sanctum::actingAs(productUserWith(['view']));

    $this->postJson('/api/products/cost-type', ['product_ids' => [$product->id], 'cost_type_id' => null])->assertForbidden();
});

it('AC-003: the mass action validates the ids and the cost type', function () {
    $product = Product::factory()->create();
    $inactive = CostType::factory()->create(['is_active' => false]);
    Sanctum::actingAs(productUserWith(['update']));

    $this->postJson('/api/products/cost-type', ['product_ids' => [], 'cost_type_id' => null])->assertJsonValidationErrors('product_ids');
    $this->postJson('/api/products/cost-type', ['product_ids' => [$product->id, 999999], 'cost_type_id' => null])->assertJsonValidationErrors('product_ids.1');
    $this->postJson('/api/products/cost-type', ['product_ids' => [$product->id, $product->id], 'cost_type_id' => null])->assertJsonValidationErrors('product_ids.0');
    $this->postJson('/api/products/cost-type', ['product_ids' => [$product->id], 'cost_type_id' => $inactive->id])->assertJsonValidationErrors('cost_type_id');
    $this->postJson('/api/products/cost-type', ['product_ids' => [$product->id]])->assertJsonValidationErrors('cost_type_id');
    $this->postJson('/api/products/cost-type', ['product_ids' => range(1, 501), 'cost_type_id' => null])->assertJsonValidationErrors('product_ids');
});

it('AC-004: for-select exposes meta.cost_type', function () {
    $costType = CostType::factory()->create(['name' => 'Utilities', 'color' => 'amber']);
    $typed = Product::factory()->create(['category_id' => ProductCategory::factory(), 'cost_type_id' => $costType->id]);
    $untyped = Product::factory()->create(['category_id' => ProductCategory::factory()]);
    Sanctum::actingAs(productUserWith(['viewAny']));

    $items = collect($this->getJson('/api/products/for-select')->assertOk()->json('items'))->keyBy('id');

    expect($items[$typed->id]['meta']['cost_type'])->toBe(['id' => $costType->id, 'name' => 'Utilities', 'color' => 'amber'])
        ->and($items[$untyped->id]['meta']['cost_type'])->toBeNull();
});

it('AC-004: the products grid shows and filters the cost type column', function () {
    $fitti = CostType::factory()->create(['name' => 'Rent', 'color' => 'blue']);
    $utenze = CostType::factory()->create(['name' => 'Utilities']);
    $withFitti = Product::factory()->create(['cost_type_id' => $fitti->id]);
    Product::factory()->create(['cost_type_id' => $utenze->id]);
    Product::factory()->create();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $columns = collect($this->getJson('/api/tables/products/columns')->assertOk()->json('data.columns'))->keyBy('id');
    expect($columns['cost_type']['filterType'])->toBe('set');

    $rows = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['cost_type' => ['filterType' => 'set', 'values' => ['Rent']]],
    ])->assertOk()->json('items');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($withFitti->id)
        ->and($rows[0]['cost_type'])->toBe(['id' => $fitti->id, 'name' => 'Rent', 'color' => 'blue']);
});
