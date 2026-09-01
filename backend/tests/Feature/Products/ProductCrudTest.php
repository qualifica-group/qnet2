<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\CustomFieldDefinition;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('productGenericFields')) {
    /**
     * The now-required generic fields (cost/price/product_type) a valid create
     * must carry, merged into each create call's payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function productGenericFields(array $overrides = []): array
    {
        return array_merge(['cost' => 10, 'price' => 20, 'product_type' => 'SERVICE'], $overrides);
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/products/{product}
// ---------------------------------------------------------------------------

it('show: 200 with category summary, no attributes key', function () {
    $actor = productUserWith(['view']);
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.category.id', $category->id);

    expect($response->json('data'))->not->toHaveKey('attributes');
    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

// ---------------------------------------------------------------------------
// AC-013 — read-only `business_function` (spec 0023)
// ---------------------------------------------------------------------------

it('show: business_function reflects the category\'s EFFECTIVE (own or inherited) function', function () {
    $actor = productUserWith(['view']);
    $function = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($root)->create();
    $withInherited = Product::factory()->create(['category_id' => $child->id]);
    $withoutFunction = Product::factory()->create(); // category_id is NOT NULL on products — every product has a category
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/{$withInherited->id}")
        ->assertOk()
        ->assertJsonPath('data.business_function.id', $function->id)
        ->assertJsonPath('data.business_function.name', $function->name);

    $this->getJson("/api/products/{$withoutFunction->id}")
        ->assertOk()
        ->assertJsonPath('data.business_function', null);
});

it('create/update: a submitted business_function_id is ignored — no column, response stays derived', function () {
    $actor = productUserWith(['create', 'update']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id, 'business_function_id' => 999999,
    ]))->assertCreated();

    $created->assertJsonPath('data.business_function.id', $function->id);

    $product = Product::where('name', 'Widget')->firstOrFail();

    $this->patchJson("/api/products/{$product->id}", ['business_function_id' => 999999])
        ->assertOk()
        ->assertJsonPath('data.business_function.id', $function->id);
});

// ---------------------------------------------------------------------------
// vat_rate_id / supplier_id (nullable references)
// ---------------------------------------------------------------------------

it('show: exposes vat_rate/supplier summaries when set, null when unset', function () {
    $actor = productUserWith(['view']);
    $vatRate = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);
    $supplier = Registry::factory()->create(['name' => 'Acme Supplies']);
    $product = Product::factory()->create(['vat_rate_id' => $vatRate->id, 'supplier_id' => $supplier->id]);
    $bare = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.vat_rate_id', $vatRate->id)
        ->assertJsonPath('data.vat_rate.id', $vatRate->id)
        ->assertJsonPath('data.vat_rate.name', 'IVA 22%')
        ->assertJsonPath('data.vat_rate.rate', '22.00')
        ->assertJsonPath('data.supplier_id', $supplier->id)
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.supplier.name', 'Acme Supplies');

    $this->getJson("/api/products/{$bare->id}")
        ->assertOk()
        ->assertJsonPath('data.vat_rate_id', null)
        ->assertJsonPath('data.vat_rate', null)
        ->assertJsonPath('data.supplier_id', null)
        ->assertJsonPath('data.supplier', null);
});

it('show: no N+1 on the vatRate/supplier relations', function () {
    $actor = productUserWith(['view']);
    $vatRate = VatRate::factory()->create();
    $supplier = Registry::factory()->create();
    $product = Product::factory()->create(['vat_rate_id' => $vatRate->id, 'supplier_id' => $supplier->id]);
    Sanctum::actingAs($actor);

    Product::preventLazyLoading();

    $this->getJson("/api/products/{$product->id}")->assertOk();

    Product::preventLazyLoading(false);
});

it('create: accepts nullable vat_rate_id/supplier_id and persists them', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $vatRate = VatRate::factory()->create();
    $supplier = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'vat_rate_id' => $vatRate->id, 'supplier_id' => $supplier->id,
    ]))->assertCreated()
        ->assertJsonPath('data.vat_rate_id', $vatRate->id)
        ->assertJsonPath('data.supplier_id', $supplier->id);

    $product = Product::where('name', 'Widget')->firstOrFail();
    expect($product->vat_rate_id)->toBe($vatRate->id)
        ->and($product->supplier_id)->toBe($supplier->id);
});

it('create: 422 when vat_rate_id/supplier_id reference a non-existent row', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id, 'vat_rate_id' => 999999,
    ]))->assertStatus(422)->assertJsonValidationErrors('vat_rate_id');

    $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id, 'supplier_id' => 999999,
    ]))->assertStatus(422)->assertJsonValidationErrors('supplier_id');
});

it('update: PATCH sets and clears vat_rate_id/supplier_id', function () {
    $actor = productUserWith(['update']);
    $vatRate = VatRate::factory()->create();
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['vat_rate_id' => $vatRate->id])
        ->assertOk()
        ->assertJsonPath('data.vat_rate_id', $vatRate->id);

    expect($product->fresh()->vat_rate_id)->toBe($vatRate->id);

    $this->patchJson("/api/products/{$product->id}", ['vat_rate_id' => null])
        ->assertOk()
        ->assertJsonPath('data.vat_rate_id', null);

    expect($product->fresh()->vat_rate_id)->toBeNull();
});

it('update: 422 when vat_rate_id references a non-existent row', function () {
    $actor = productUserWith(['update']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['vat_rate_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('vat_rate_id');
});

it('show: 403 without products.view / 404 for a non-existent product', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);
    $this->getJson("/api/products/{$product->id}")->assertForbidden();

    $actor = productUserWith(['view']);
    Sanctum::actingAs($actor);
    $this->getJson('/api/products/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/products
// ---------------------------------------------------------------------------

it('create: 201, persists only the generic fields, response has no attributes key', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
    ]))->assertCreated();

    $product = Product::where('name', 'Widget')->firstOrFail();
    expect($product->product_type->value)->toBe('SERVICE')
        ->and((float) $product->cost)->toBe(10.0)
        ->and((float) $product->price)->toBe(20.0);
    expect($response->json('data'))->not->toHaveKey('attributes');
});

it('create: a submitted `attributes` key is ignored — not validated, not persisted', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $foreignAttribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attributes' => [['attribute_id' => $foreignAttribute->id, 'value' => 'x']],
    ]))->assertCreated();

    expect($response->json('data'))->not->toHaveKey('attributes');
});

it('create: 403 without products.create / 422 without the required generic fields', function () {
    $category = ProductCategory::factory()->create();

    $actor = productUserWith([]);
    Sanctum::actingAs($actor);
    $this->postJson('/api/products', productGenericFields(['name' => 'X', 'category_id' => $category->id]))->assertForbidden();

    $actor = productUserWith(['create']);
    Sanctum::actingAs($actor);
    $this->postJson('/api/products', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'category_id', 'cost', 'price', 'product_type']);
});

it('create: invalid product_type → 422', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'X', 'category_id' => $category->id, 'product_type' => 'GOODS',
    ]))->assertStatus(422)->assertJsonValidationErrors('product_type');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/products/{product}
// ---------------------------------------------------------------------------

it('update: a submitted `attributes` key is ignored — not validated, not persisted', function () {
    $actor = productUserWith(['update']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/products/{$product->id}", [
        'attributes' => [['attribute_id' => 999999, 'value' => 'x']],
    ])->assertOk();

    expect($response->json('data'))->not->toHaveKey('attributes');
});

it('update: only custom_fields (no native attribute) persists and reads back the value', function () {
    CustomFieldDefinition::factory()->forEntity('products')->ofType('text')->create(['key' => 'notes']);

    $actor = productUserWith(['view', 'update']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", [
        'custom_fields' => ['notes' => 'written on a fields-only edit'],
    ])->assertOk()
        ->assertJsonPath('data.custom_fields.notes', 'written on a fields-only edit');

    expect($product->fresh()->custom_fields)->toBe(['notes' => 'written on a fields-only edit']);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.custom_fields.notes', 'written on a fields-only edit');
});

it('update: product_type patch is validated against the enum (invalid → 422, valid → 200)', function () {
    $actor = productUserWith(['update']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'GOODS'])
        ->assertStatus(422)->assertJsonValidationErrors('product_type');

    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'SERVICE'])->assertOk();
    expect($product->fresh()->product_type->value)->toBe('SERVICE');
});

it('update: 403 without products.update / 404 for a non-existent product', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);
    $this->patchJson("/api/products/{$product->id}", ['name' => 'Nope'])->assertForbidden();

    $actor = productUserWith(['update']);
    Sanctum::actingAs($actor);
    $this->patchJson('/api/products/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/products/{product}
// ---------------------------------------------------------------------------

it('delete: 204', function () {
    $actor = productUserWith(['delete']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

it('delete: 403 without products.delete', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/products/{$product->id}")->assertForbidden();
});
