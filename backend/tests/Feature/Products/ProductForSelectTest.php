<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * GET /api/products/for-select (ADR 0011) — feeds the "prodotti di interesse"
 * picker. `category_ids[]` is the default scoping the picker sends; omitting
 * it is the operator's explicit "unlock the whole catalogue".
 */
uses(RefreshDatabase::class);

if (! function_exists('productForSelectActor')) {
    function productForSelectActor(bool $withPermission = true): User
    {
        Permission::findOrCreate('products.viewAny');
        Permission::findOrCreate('products.view');

        $user = User::factory()->create();

        if ($withPermission) {
            $user->givePermissionTo('products.viewAny');
        }

        return $user;
    }
}

it('returns id/label/subtitle (its category) for every product', function () {
    $actor = productForSelectActor();
    $category = ProductCategory::factory()->create(['name' => 'Fibra']);
    $product = Product::factory()->create(['name' => 'Fibra 1Gb', 'category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/products/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.id', $product->id)
        ->assertJsonPath('items.0.label', 'Fibra 1Gb')
        ->assertJsonPath('items.0.subtitle', 'Fibra');
});

it('category_ids[] scopes the page to those categories', function () {
    $actor = productForSelectActor();
    $wanted = ProductCategory::factory()->create();
    $other = ProductCategory::factory()->create();
    $inScope = Product::factory()->create(['category_id' => $wanted->id]);
    Product::factory()->create(['category_id' => $other->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/products/for-select?category_ids[]={$wanted->id}")
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $inScope->id)
        ->assertJsonPath('pagination.total', 1);
});

it('ids[] hydration bypasses the category scope so a selected product keeps its label', function () {
    $actor = productForSelectActor();
    $wanted = ProductCategory::factory()->create();
    $other = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $wanted->id]);
    $outside = Product::factory()->create(['category_id' => $other->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/for-select?category_ids[]={$wanted->id}&ids[]={$outside->id}")
        ->assertOk()
        ->assertJsonCount(2, 'items');

    // The hydrated id is appended, never counted in the total.
    expect($response->json('pagination.total'))->toBe(1)
        ->and(collect($response->json('items'))->pluck('id'))->toContain($outside->id);
});

it('search filters by name', function () {
    $actor = productForSelectActor();
    Product::factory()->create(['name' => 'Fibra 1Gb']);
    Product::factory()->create(['name' => 'Mobile 100Gb']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/products/for-select?search=Fibra')
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.label', 'Fibra 1Gb');
});

it('an unknown category_id -> 422', function () {
    Sanctum::actingAs(productForSelectActor());

    $this->getJson('/api/products/for-select?category_ids[]=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_ids.0');
});

it('without products.viewAny -> 200 (ADR 0011 amended)', function () {
    Sanctum::actingAs(productForSelectActor(withPermission: false));

    $this->getJson('/api/products/for-select')->assertOk();
});

// ---------------------------------------------------------------------------
// meta block (spec 0065, AC-009): code/price/cost/vat_rate_id/vat_rate_name/vat_rate
// ---------------------------------------------------------------------------

it('every item exposes meta.code/price/cost/vat_rate_* without changing id/label/subtitle (AC-009)', function () {
    $actor = productForSelectActor();
    $category = ProductCategory::factory()->create(['name' => 'Fibra']);
    $vatRate = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);
    $product = Product::factory()->create([
        'name' => 'Fibra 1Gb', 'category_id' => $category->id, 'code' => 'PRD-0042',
        'price' => 99.90, 'cost' => 40, 'vat_rate_id' => $vatRate->id,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/products/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.id', $product->id)
        ->assertJsonPath('items.0.label', 'Fibra 1Gb')
        ->assertJsonPath('items.0.subtitle', 'Fibra')
        // Spec 0075 AC-012: the category as an ID, next to its human
        // `subtitle` — the request-management forms drop a selected product as
        // soon as its category leaves the request's product lines.
        ->assertJsonPath('items.0.meta.category_id', $category->id)
        ->assertJsonPath('items.0.meta.code', 'PRD-0042')
        ->assertJsonPath('items.0.meta.price', '99.90')
        ->assertJsonPath('items.0.meta.cost', '40.00')
        ->assertJsonPath('items.0.meta.vat_rate_id', $vatRate->id)
        ->assertJsonPath('items.0.meta.vat_rate_name', 'IVA 22%')
        ->assertJsonPath('items.0.meta.vat_rate', '22.00');
});

it('meta.vat_rate_* is null when the product has no vat_rate_id (AC-009)', function () {
    $actor = productForSelectActor();
    Product::factory()->create(['name' => 'No VAT product', 'vat_rate_id' => null]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/products/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.meta.vat_rate_id', null)
        ->assertJsonPath('items.0.meta.vat_rate_name', null)
        ->assertJsonPath('items.0.meta.vat_rate', null);
});

it('no N+1 on the vatRate relation across the page (AC-009)', function () {
    $actor = productForSelectActor();
    $vatRate = VatRate::factory()->create();
    Product::factory()->count(5)->create(['vat_rate_id' => $vatRate->id]);
    Sanctum::actingAs($actor);

    Product::preventLazyLoading();

    $this->getJson('/api/products/for-select')->assertOk();

    Product::preventLazyLoading(false);
});
