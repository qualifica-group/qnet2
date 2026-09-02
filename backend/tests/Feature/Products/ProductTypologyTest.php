<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0099, D-3: products.product_typology_id delta. A dedicated file
// (rather than extending ProductCrudTest.php) keeps this addition isolated
// from the rest of the products test suite's own churn — same arrangement as
// ProductUnitOfMeasureTest.
uses(RefreshDatabase::class);

if (! function_exists('productTypologyProductUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productTypologyProductUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — the migration backfilled every pre-existing product
// ---------------------------------------------------------------------------

it('schema: product_typology_id is NOT NULL and every product carries one (AC-003)', function () {
    $product = Product::factory()->create();

    expect($product->product_typology_id)->not->toBeNull();
    expect(Product::whereNull('product_typology_id')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-030/031 — server-side default resolution
// ---------------------------------------------------------------------------

it('create: POST without product_typology_id resolves the default typology (AC-030)', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(productTypologyProductUserWith(['create']));

    $response = $this->postJson('/api/products', [
        'name' => 'No Typology', 'cost' => 10, 'price' => 20, 'category_id' => $category->id, 'product_type' => 'SERVICE',
    ])->assertCreated();

    $default = ProductTypology::where('code', 'institution')->first();
    expect($response->json('data.product_typology_id'))->toBe($default->id)
        ->and($response->json('data.product_typology.name'))->toBe($default->name);
});

it('create: POST with product_typology_id explicitly null also resolves the default (AC-030)', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(productTypologyProductUserWith(['create']));

    $response = $this->postJson('/api/products', [
        'name' => 'Null Typology', 'cost' => 10, 'price' => 20, 'category_id' => $category->id,
        'product_type' => 'SERVICE', 'product_typology_id' => null,
    ])->assertCreated();

    expect($response->json('data.product_typology_id'))->toBe(ProductTypology::where('code', 'institution')->value('id'));
});

it('create: POST with an explicit product_typology_id uses it (AC-031)', function () {
    $category = ProductCategory::factory()->create();
    $typology = ProductTypology::factory()->create(['name' => 'Consulenza', 'code' => 'consultancy']);
    Sanctum::actingAs(productTypologyProductUserWith(['create']));

    $this->postJson('/api/products', [
        'name' => 'Typed', 'cost' => 10, 'price' => 20, 'category_id' => $category->id,
        'product_type' => 'SERVICE', 'product_typology_id' => $typology->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.product_typology_id', $typology->id)
        ->assertJsonPath('data.product_typology.name', 'Consulenza');
});

it('create: 422 when product_typology_id does not exist', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(productTypologyProductUserWith(['create']));

    $this->postJson('/api/products', [
        'name' => 'Bad', 'cost' => 10, 'price' => 20, 'category_id' => $category->id,
        'product_type' => 'SERVICE', 'product_typology_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('product_typology_id');
});

it('update: PATCH switches the typology (AC-031)', function () {
    $product = Product::factory()->create();
    $typology = ProductTypology::factory()->create();
    Sanctum::actingAs(productTypologyProductUserWith(['update']));

    $this->patchJson("/api/products/{$product->id}", ['product_typology_id' => $typology->id])
        ->assertOk()
        ->assertJsonPath('data.product_typology_id', $typology->id);
});

it('update: PATCH with product_typology_id null resets to the default (AC-030)', function () {
    $product = Product::factory()->create();
    Sanctum::actingAs(productTypologyProductUserWith(['update']));

    $this->patchJson("/api/products/{$product->id}", ['product_typology_id' => null])
        ->assertOk()
        ->assertJsonPath('data.product_typology_id', ProductTypology::where('code', 'institution')->value('id'));
});

// ---------------------------------------------------------------------------
// AC-032 — resource shape
// ---------------------------------------------------------------------------

it('show: ProductResource exposes product_typology_id and product_typology (AC-032)', function () {
    $typology = ProductTypology::factory()->create(['name' => 'Ente Locale']);
    $product = Product::factory()->create(['product_typology_id' => $typology->id]);
    Sanctum::actingAs(productTypologyProductUserWith(['view']));

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.product_typology_id', $typology->id)
        ->assertJsonPath('data.product_typology', ['id' => $typology->id, 'name' => 'Ente Locale']);
});

it('for-select: the product meta carries its typology (feeds the offer live summary)', function () {
    $typology = ProductTypology::factory()->create(['name' => 'Consulenza']);
    $product = Product::factory()->create(['name' => 'Servizio', 'product_typology_id' => $typology->id]);
    Sanctum::actingAs(productTypologyProductUserWith([]));

    $this->getJson('/api/products/for-select?ids[]='.$product->id)
        ->assertOk()
        ->assertJsonPath('items.0.meta.product_typology', ['id' => $typology->id, 'name' => 'Consulenza']);
});

// ---------------------------------------------------------------------------
// AC-033 — the products grid column
// ---------------------------------------------------------------------------

it('table: the products grid exposes a product_typology column, distinct from product_type (AC-033)', function () {
    Sanctum::actingAs(productTypologyProductUserWith(['viewAny']));

    $columns = $this->getJson('/api/tables/products/columns')->assertOk()->json('data.columns');
    $ids = array_column($columns, 'id');

    expect($ids)->toContain('product_typology')->toContain('product_type');
});

it('table: rows project the typology and the set filter narrows to it (AC-033)', function () {
    // "Ente" is already taken by the migration's default row (AC-002) and
    // `name` is unique, so the fixtures use distinct names.
    $first = ProductTypology::factory()->create(['name' => 'Ente Locale']);
    $second = ProductTypology::factory()->create(['name' => 'Consulenza']);
    Product::factory()->create(['name' => 'A', 'product_typology_id' => $first->id]);
    Product::factory()->create(['name' => 'B', 'product_typology_id' => $second->id]);
    Sanctum::actingAs(productTypologyProductUserWith(['viewAny']));

    $all = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items');
    expect(collect($all)->pluck('product_typology.name')->filter()->values()->all())
        ->toContain('Ente Locale')->toContain('Consulenza');

    $filtered = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['product_typology' => ['filterType' => 'set', 'values' => ['Ente Locale']]],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('name')->all())->toBe(['A']);
});
