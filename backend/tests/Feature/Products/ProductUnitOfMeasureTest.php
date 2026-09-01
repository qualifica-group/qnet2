<?php

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0088, D-4: products.unit_of_measure_id delta. A dedicated file
// (rather than extending ProductCrudTest.php) keeps this addition isolated
// from the rest of the products test suite's own churn.
uses(RefreshDatabase::class);

if (! function_exists('productUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productUserWith(array $abilities): User
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
// AC-040 — absent unit_of_measure_id falls back to the default unit
// ---------------------------------------------------------------------------

it('create: POST without unit_of_measure_id resolves the default unit (AC-040)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/products', [
        'name' => 'No Unit', 'cost' => 10, 'price' => 20, 'category_id' => $category->id, 'product_type' => 'SERVICE',
    ])->assertCreated();

    $defaultUnit = UnitOfMeasure::where('code', 'unit')->first();
    expect($response->json('data.unit_of_measure_id'))->toBe($defaultUnit->id)
        ->and($response->json('data.unit_of_measure.symbol'))->toBe($defaultUnit->symbol);
});

it('create: POST with unit_of_measure_id explicitly null also resolves the default unit (AC-040)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/products', [
        'name' => 'Null Unit', 'cost' => 10, 'price' => 20, 'category_id' => $category->id, 'product_type' => 'SERVICE',
        'unit_of_measure_id' => null,
    ])->assertCreated();

    $defaultUnit = UnitOfMeasure::where('code', 'unit')->first();
    expect($response->json('data.unit_of_measure_id'))->toBe($defaultUnit->id);
});

// ---------------------------------------------------------------------------
// AC-041 — an explicit unit_of_measure_id is used as submitted
// ---------------------------------------------------------------------------

it('create: POST with unit_of_measure_id uses that unit (AC-041)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $unit = UnitOfMeasure::factory()->create(['name' => 'Chilogrammi', 'symbol' => 'kg']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/products', [
        'name' => 'Weighed Product', 'cost' => 10, 'price' => 20, 'category_id' => $category->id, 'product_type' => 'SERVICE',
        'unit_of_measure_id' => $unit->id,
    ])->assertCreated();

    expect($response->json('data.unit_of_measure_id'))->toBe($unit->id)
        ->and($response->json('data.unit_of_measure.name'))->toBe('Chilogrammi');
});

it('create: POST with a non-existent unit_of_measure_id -> 422', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', [
        'name' => 'Bad Unit', 'cost' => 10, 'price' => 20, 'category_id' => $category->id, 'product_type' => 'SERVICE',
        'unit_of_measure_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('unit_of_measure_id');
});

it('update: PATCH with a new unit_of_measure_id changes it (AC-041)', function () {
    $actor = productUserWith(['update']);
    $originalUnit = UnitOfMeasure::factory()->create();
    $newUnit = UnitOfMeasure::factory()->create(['name' => 'Litri', 'symbol' => 'l']);
    $product = Product::factory()->create(['unit_of_measure_id' => $originalUnit->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['unit_of_measure_id' => $newUnit->id])
        ->assertOk()
        ->assertJsonPath('data.unit_of_measure_id', $newUnit->id)
        ->assertJsonPath('data.unit_of_measure.name', 'Litri');
});

it('update: PATCH without unit_of_measure_id leaves the persisted FK untouched', function () {
    $actor = productUserWith(['update']);
    $unit = UnitOfMeasure::factory()->create();
    $product = Product::factory()->create(['unit_of_measure_id' => $unit->id, 'name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.unit_of_measure_id', $unit->id);
});

it('update: PATCH with unit_of_measure_id explicitly null resets to the default unit', function () {
    $actor = productUserWith(['update']);
    $unit = UnitOfMeasure::factory()->create();
    $product = Product::factory()->create(['unit_of_measure_id' => $unit->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/products/{$product->id}", ['unit_of_measure_id' => null])->assertOk();

    $defaultUnit = UnitOfMeasure::where('code', 'unit')->first();
    expect($response->json('data.unit_of_measure_id'))->toBe($defaultUnit->id);
});

// ---------------------------------------------------------------------------
// AC-042 — ProductResource exposes unit_of_measure_id + unit_of_measure
// ---------------------------------------------------------------------------

it('show: ProductResource exposes unit_of_measure_id and unit_of_measure {id,name,symbol} (AC-042)', function () {
    $actor = productUserWith(['view']);
    $unit = UnitOfMeasure::factory()->create(['name' => 'Metri', 'symbol' => 'm']);
    $product = Product::factory()->create(['unit_of_measure_id' => $unit->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.unit_of_measure_id', $unit->id)
        ->assertJsonPath('data.unit_of_measure', ['id' => $unit->id, 'name' => 'Metri', 'symbol' => 'm']);
});

// ---------------------------------------------------------------------------
// AC-043 — delete does not cascade to the unit; a used unit 409s (see also
// UnitOfMeasureCrudTest AC-015)
// ---------------------------------------------------------------------------

it('delete: deleting a product does NOT delete its unit of measure (AC-043)', function () {
    $actor = productUserWith(['delete']);
    $unit = UnitOfMeasure::factory()->create();
    $product = Product::factory()->create(['unit_of_measure_id' => $unit->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

    $this->assertDatabaseHas('units_of_measure', ['id' => $unit->id]);
});

// ---------------------------------------------------------------------------
// ProductService::create() always resolves a real, persisted default unit
// ---------------------------------------------------------------------------

it('ProductService::create() default unit resolution points at a real row', function () {
    $product = app(ProductService::class)->create(new CreateProductData(
        name: 'Service default',
        description: null,
        cost: 1,
        price: 2,
        categoryId: ProductCategory::factory()->create()->id,
        productType: ProductType::Service,
    ));

    $defaultUnit = UnitOfMeasure::where('code', 'unit')->first();
    expect($product->unit_of_measure_id)->toBe($defaultUnit->id);
});
