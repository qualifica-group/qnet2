<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\Registry;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0320: a product has 0..N suppliers, each with its own cost, one of them
 * the default. AC-002 (write), AC-003 (cost by usage), AC-004 (for-select),
 * AC-005 (registry delete guard).
 */
/** @param  array<string, mixed>  $overrides */
function supplierProductPayload(array $overrides = []): array
{
    return array_merge(['cost' => 10, 'price' => 20, 'product_type' => 'SERVICE'], $overrides);
}

function supplierRow(Registry $supplier, float|string $cost = 10, ?bool $default = null, float|string|null $price = null): array
{
    return array_filter(
        ['supplier_id' => $supplier->id, 'price' => $price, 'cost' => $cost, 'is_default' => $default],
        static fn (mixed $value): bool => $value !== null,
    );
}

function restrictSuppliersField(object $user, bool $visible, bool $editable): void
{
    $role = Role::create(['name' => 'catalog-reader']);
    $user->assignRole($role);
    RoleFieldPermission::query()->create([
        'role_id' => $role->id, 'resource' => 'products', 'field' => 'suppliers',
        'visible' => $visible, 'editable' => $editable, 'required' => false,
    ]);
}

// --- AC-002 create -----------------------------------------------------------

it('create: stores two suppliers with one default and returns them default first', function () {
    $category = ProductCategory::factory()->create();
    $zeta = Registry::factory()->supplier()->create(['name' => 'Zeta']);
    $alpha = Registry::factory()->supplier()->create(['name' => 'Alpha']);
    Sanctum::actingAs(productUserWith(['create']));

    $this->postJson('/api/products', supplierProductPayload([
        'name' => 'Widget', 'category_id' => $category->id,
        'suppliers' => [supplierRow($alpha, 5, false), supplierRow($zeta, '7.50', true)],
    ]))->assertCreated()
        ->assertJsonPath('data.suppliers', [
            ['supplier_id' => $zeta->id, 'name' => 'Zeta', 'price' => '0.00', 'cost' => '7.50', 'is_default' => true, 'commission' => null],
            ['supplier_id' => $alpha->id, 'name' => 'Alpha', 'price' => '0.00', 'cost' => '5.00', 'is_default' => false, 'commission' => null],
        ]);

    expect(ProductSupplier::query()->count())->toBe(2);
});

it('create: a single supplier without the flag is forced to default', function () {
    $category = ProductCategory::factory()->create();
    $supplier = Registry::factory()->supplier()->create();
    Sanctum::actingAs(productUserWith(['create']));

    $this->postJson('/api/products', supplierProductPayload([
        'name' => 'Widget', 'category_id' => $category->id,
        'suppliers' => [supplierRow($supplier, 3)],
    ]))->assertCreated()->assertJsonPath('data.suppliers.0.is_default', true);

    $this->postJson('/api/products', supplierProductPayload([
        'name' => 'Widget 2', 'category_id' => $category->id,
        'suppliers' => [supplierRow($supplier, 3, false)],
    ]))->assertCreated()->assertJsonPath('data.suppliers.0.is_default', true);
});

it('create: 422 when two suppliers have no default or two defaults', function () {
    $category = ProductCategory::factory()->create();
    [$a, $b] = [Registry::factory()->supplier()->create(), Registry::factory()->supplier()->create()];
    Sanctum::actingAs(productUserWith(['create']));
    $base = supplierProductPayload(['name' => 'Widget', 'category_id' => $category->id]);

    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($a, 1, false), supplierRow($b, 2, false)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers');
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($a, 1, true), supplierRow($b, 2, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers');

    expect(Product::query()->count())->toBe(0);
});

it('create: 422 on a duplicated supplier, a non-supplier registry, a bad price or cost and the old supplier_id key', function () {
    $category = ProductCategory::factory()->create();
    $supplier = Registry::factory()->supplier()->create();
    $client = Registry::factory()->create(['is_supplier' => false]);
    Sanctum::actingAs(productUserWith(['create']));
    $base = supplierProductPayload(['name' => 'Widget', 'category_id' => $category->id]);

    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, 1, true), supplierRow($supplier, 2, false)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.1.supplier_id');
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($client, 1, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.supplier_id');
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, 1, true, -1)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.price');
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, -1, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.cost');
    $this->postJson('/api/products', $base + ['supplier_id' => $supplier->id])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
});

// --- AC-002 update -----------------------------------------------------------

it('update: replaces the list, leaves it untouched when absent and empties it with []', function () {
    $product = Product::factory()->create();
    [$a, $b] = [Registry::factory()->supplier()->create(['name' => 'A']), Registry::factory()->supplier()->create(['name' => 'B'])];
    ProductSupplier::factory()->for($product)->default()->create(['supplier_id' => $a->id, 'cost' => 1]);
    Sanctum::actingAs(productUserWith(['update']));

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonCount(1, 'data.suppliers')
        ->assertJsonPath('data.suppliers.0.supplier_id', $a->id);

    $this->patchJson("/api/products/{$product->id}", ['suppliers' => [supplierRow($a, 2, false), supplierRow($b, 3, true)]])
        ->assertOk()
        ->assertJsonPath('data.suppliers.0.supplier_id', $b->id)
        ->assertJsonPath('data.suppliers.1.cost', '2.00');
    expect(ProductSupplier::query()->where('product_id', $product->id)->count())->toBe(2);

    $this->patchJson("/api/products/{$product->id}", ['suppliers' => [supplierRow($b, 3)]])
        ->assertOk()
        ->assertJsonCount(1, 'data.suppliers');
    expect(ProductSupplier::query()->where('product_id', $product->id)->count())->toBe(1);

    $this->patchJson("/api/products/{$product->id}", ['suppliers' => []])
        ->assertOk()
        ->assertJsonPath('data.suppliers', []);
    expect(ProductSupplier::query()->where('product_id', $product->id)->count())->toBe(0);
});

it('update: 422 on supplier_id and on a list with no default', function () {
    $product = Product::factory()->create();
    [$a, $b] = [Registry::factory()->supplier()->create(), Registry::factory()->supplier()->create()];
    Sanctum::actingAs(productUserWith(['update']));

    $this->patchJson("/api/products/{$product->id}", ['supplier_id' => $a->id])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    $this->patchJson("/api/products/{$product->id}", ['suppliers' => [supplierRow($a, 1, false), supplierRow($b, 2, false)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers');
});

// --- AC-003 ------------------------------------------------------------------

it('AC-027: cost is never required, with or without suppliers: absent or null is 0', function () {
    $category = ProductCategory::factory()->create();
    $supplier = Registry::factory()->supplier()->create();
    Sanctum::actingAs(productUserWith(['create', 'update']));
    $base = ['name' => 'Costo', 'category_id' => $category->id, 'product_type' => 'SERVICE', 'usages' => ['COST'], 'cost' => null];

    $this->postJson('/api/products', $base)->assertCreated()->assertJsonPath('data.cost', '0.00');
    $this->postJson('/api/products', $base + ['suppliers' => []])->assertCreated();
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, 4)]])->assertCreated();

    $product = Product::factory()->create(['usages' => ['COST'], 'cost' => 5]);
    ProductSupplier::factory()->for($product)->default()->create(['supplier_id' => $supplier->id]);
    $this->patchJson("/api/products/{$product->id}", ['cost' => null, 'suppliers' => []])->assertOk();
    expect($product->fresh()->cost)->toBe('0.00');
});

// --- field permission `suppliers` --------------------------------------------

it('a read-only suppliers field rejects a change but accepts the unchanged list', function () {
    $product = Product::factory()->create();
    $supplier = Registry::factory()->supplier()->create();
    ProductSupplier::factory()->for($product)->default()->create(['supplier_id' => $supplier->id, 'cost' => 10, 'price' => 20]);
    $actor = productUserWith(['view', 'update']);
    restrictSuppliersField($actor, visible: true, editable: false);
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.suppliers.editable', false);

    $this->patchJson("/api/products/{$product->id}", ['suppliers' => [supplierRow($supplier, 11, true, 20)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers');
    $this->patchJson("/api/products/{$product->id}", ['name' => 'Same list', 'suppliers' => [supplierRow($supplier, 10, true, 20)]])
        ->assertOk();
    expect(ProductSupplier::query()->where('product_id', $product->id)->value('cost'))->toBe('10.00');
});

it('a hidden suppliers field drops the key from the resource', function () {
    $product = Product::factory()->create();
    ProductSupplier::factory()->for($product)->default()->create();
    $actor = productUserWith(['view']);
    restrictSuppliersField($actor, visible: false, editable: false);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/{$product->id}")->assertOk();

    expect($response->json('data'))->not->toHaveKey('suppliers');
});

// --- AC-004 ------------------------------------------------------------------

it('for-select exposes meta.suppliers default first, also through ids[]', function () {
    Permission::findOrCreate('products.viewAny');
    $actor = productUserWith(['viewAny']);
    $product = Product::factory()->create(['cost' => 9]);
    $other = Product::factory()->create();
    $b = Registry::factory()->supplier()->create(['name' => 'Bravo']);
    $a = Registry::factory()->supplier()->create(['name' => 'Alfa']);
    ProductSupplier::factory()->for($product)->create(['supplier_id' => $a->id, 'cost' => 3, 'price' => 30]);
    ProductSupplier::factory()->for($product)->default()->create(['supplier_id' => $b->id, 'cost' => 4, 'price' => 40]);
    Sanctum::actingAs($actor);

    $expected = [
        ['supplier_id' => $b->id, 'name' => 'Bravo', 'price' => '40.00', 'cost' => '4.00', 'is_default' => true],
        ['supplier_id' => $a->id, 'name' => 'Alfa', 'price' => '30.00', 'cost' => '3.00', 'is_default' => false],
    ];

    $items = collect($this->getJson('/api/products/for-select')->assertOk()->json('items'))->keyBy('id');
    expect($items[$product->id]['meta']['suppliers'])->toBe($expected)
        ->and($items[$product->id]['meta']['cost'])->toBe('9.00')
        ->and($items[$other->id]['meta']['suppliers'])->toBe([]);

    $this->getJson("/api/products/for-select?search=zzz-no-match&ids[]={$product->id}")
        ->assertOk()
        ->assertJsonPath('items.0.id', $product->id)
        ->assertJsonPath('items.0.meta.suppliers', $expected);
});

// --- AC-005 ------------------------------------------------------------------

it('registry delete: 409 when it supplies a product, still deletable otherwise', function () {
    Permission::findOrCreate('registries.delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('registries.delete');
    $used = Registry::factory()->supplier()->create();
    $free = Registry::factory()->supplier()->create();
    ProductSupplier::factory()->default()->create(['supplier_id' => $used->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/registries/{$used->id}")->assertStatus(409);
    expect(Registry::query()->whereKey($used->id)->exists())->toBeTrue();

    $this->deleteJson("/api/registries/{$free->id}")->assertSuccessful();
    expect(Registry::query()->whereKey($free->id)->exists())->toBeFalse();
});

// --- Spec 0320 rev. 2: AC-019, price per supplier ---------------------------

it('AC-019: stores and returns the supplier price; an absent price or cost is 0', function () {
    $category = ProductCategory::factory()->create();
    $priced = Registry::factory()->supplier()->create(['name' => 'Priced']);
    $bare = Registry::factory()->supplier()->create(['name' => 'Bare']);
    Sanctum::actingAs(productUserWith(['create', 'update']));

    $id = $this->postJson('/api/products', supplierProductPayload([
        'name' => 'Widget', 'category_id' => $category->id,
        'suppliers' => [supplierRow($priced, 4, true, '99.5'), ['supplier_id' => $bare->id, 'is_default' => false]],
    ]))->assertCreated()
        ->assertJsonPath('data.suppliers', [
            ['supplier_id' => $priced->id, 'name' => 'Priced', 'price' => '99.50', 'cost' => '4.00', 'is_default' => true, 'commission' => null],
            ['supplier_id' => $bare->id, 'name' => 'Bare', 'price' => '0.00', 'cost' => '0.00', 'is_default' => false, 'commission' => null],
        ])->json('data.id');

    $this->patchJson("/api/products/{$id}", ['suppliers' => [supplierRow($priced, 4, true, 120), ['supplier_id' => $bare->id, 'price' => null, 'cost' => null]]])
        ->assertOk()
        ->assertJsonPath('data.suppliers.0.price', '120.00')
        ->assertJsonPath('data.suppliers.1.price', '0.00');
    expect(ProductSupplier::query()->where('product_id', $id)->where('supplier_id', $priced->id)->value('price'))->toBe('120.00');
});

it('AC-019: a negative or over-precise supplier price is a 422', function () {
    $category = ProductCategory::factory()->create();
    $supplier = Registry::factory()->supplier()->create();
    Sanctum::actingAs(productUserWith(['create']));
    $base = supplierProductPayload(['name' => 'Widget', 'category_id' => $category->id]);

    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, 1, true, -5)]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.price');
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, 1, true, '1.234')]])
        ->assertUnprocessable()->assertJsonValidationErrors('suppliers.0.price');
});

it('AC-027: the standard price is never required, with or without suppliers: absent or null is 0', function () {
    $category = ProductCategory::factory()->create();
    $supplier = Registry::factory()->supplier()->create();
    Sanctum::actingAs(productUserWith(['create', 'update']));
    $base = ['name' => 'Servizio', 'category_id' => $category->id, 'product_type' => 'SERVICE', 'usages' => ['SALE'], 'price' => null];

    $this->postJson('/api/products', $base)->assertCreated()->assertJsonPath('data.price', '0.00');
    $this->postJson('/api/products', $base + ['suppliers' => []])->assertCreated();
    $this->postJson('/api/products', $base + ['suppliers' => [supplierRow($supplier, 1, true, 50)]])->assertCreated();

    $product = Product::factory()->create(['usages' => ['SALE'], 'price' => 5]);
    ProductSupplier::factory()->for($product)->default()->create(['supplier_id' => $supplier->id]);
    $this->patchJson("/api/products/{$product->id}", ['price' => null, 'suppliers' => []])->assertOk();
    expect($product->fresh()->price)->toBe('0.00');
});
