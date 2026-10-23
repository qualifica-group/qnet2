<?php

use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function stockProductActor(array $stockAbilities = []): User
{
    $user = productUserWith(['viewAny', 'view', 'create', 'update', 'delete']);

    foreach ($stockAbilities as $ability) {
        Permission::findOrCreate("stock.{$ability}");
        $user->givePermissionTo("stock.{$ability}");
    }

    return $user;
}

it('0307 AC-001: INTERNAL is refused on a service and accepted on a physical good, which becomes tracked', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(stockProductActor());
    $base = ['name' => 'Notebook', 'category_id' => $category->id, 'usages' => ['INTERNAL']];

    $this->postJson('/api/products', [...$base, 'product_type' => 'SERVICE'])
        ->assertUnprocessable()->assertJsonValidationErrors('usages');
    expect(Product::count())->toBe(0);

    $created = $this->postJson('/api/products', [...$base, 'product_type' => 'PHYSICAL'])->assertCreated();
    expect($created->json('data.is_stock_tracked'))->toBeTrue()
        ->and($created->json('data.product_type'))->toBe('PHYSICAL')
        ->and($created->json('data.usages'))->toBe(['INTERNAL']);

    $service = Product::factory()->create(['product_type' => ProductType::Service, 'usages' => [ProductUsage::Sale]]);
    $this->patchJson("/api/products/{$service->id}", ['usages' => ['SALE', 'INTERNAL']])
        ->assertUnprocessable()->assertJsonValidationErrors('usages');
    expect($service->fresh()->usages->all())->toBe([ProductUsage::Sale]);

    $this->patchJson("/api/products/{$service->id}", ['product_type' => 'PHYSICAL', 'usages' => ['SALE', 'INTERNAL']])->assertOk();
    expect($service->fresh()->isStockTracked())->toBeTrue();
});

it('0307 AC-001: on update the persisted usages count when only the type is sent', function () {
    $product = stockTrackedProduct();
    Sanctum::actingAs(stockProductActor());

    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'SERVICE'])
        ->assertUnprocessable()->assertJsonValidationErrors('usages');

    expect($product->fresh()->product_type)->toBe(ProductType::Physical);
});

it('0307 AC-002: a product with stock cannot lose INTERNAL or its physical type, with no stock it can', function () {
    $product = stockTrackedProduct();
    $balance = stockSeed($product, OperationalSite::factory()->create(), '2.000');
    Sanctum::actingAs(stockProductActor());

    $this->patchJson("/api/products/{$product->id}", ['usages' => ['SALE'], 'price' => 10])
        ->assertUnprocessable()->assertJsonValidationErrors('usages');
    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'SERVICE'])
        ->assertUnprocessable()->assertJsonValidationErrors('product_type');

    expect($product->fresh())->product_type->toBe(ProductType::Physical)
        ->and($product->fresh()->usages->all())->toBe([ProductUsage::Internal]);

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed'])->assertOk();

    $balance->delete();
    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'SERVICE', 'usages' => ['SALE'], 'price' => 10])->assertOk();
    expect($product->fresh()->isStockTracked())->toBeFalse();
});

it('0307 AC-012: the products grid shows the stock sum for tracked products only, to who can read the stock', function () {
    $tracked = Product::factory()->internalUse()->create(['name' => 'Tracked']);
    $untracked = Product::factory()->create(['name' => 'Untracked']);
    $empty = Product::factory()->internalUse()->create(['name' => 'Empty']);
    stockSeed($tracked, OperationalSite::factory()->create(), '2.500');
    stockSeed($tracked, OperationalSite::factory()->create(), '1.000');
    $rowsOf = fn (array $body = []) => collect($this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25, ...$body])->assertOk()->json('items'));

    Sanctum::actingAs(stockProductActor(['viewAny']));
    expect($rowsOf()->pluck('stock_quantity', 'name')->all())->toEqual(['Tracked' => '3.500', 'Untracked' => null, 'Empty' => '0.000']);

    $sorted = $rowsOf(['sortModel' => [['colId' => 'stock_quantity', 'sort' => 'desc']]]);
    expect($sorted->first()['name'])->toBe('Tracked');

    Sanctum::actingAs(stockProductActor());
    expect($rowsOf()->pluck('stock_quantity')->filter()->all())->toBe([]);

    $column = collect($this->getJson('/api/tables/products/columns')->json('data.columns'))->firstWhere('id', 'stock_quantity');
    expect($column)->toMatchArray(['type' => 'number', 'sortable' => true, 'filterable' => false]);
});

it('0307 AC-012: the product detail carries is_stock_tracked and stock_quantity, null without stock.viewAny', function () {
    $product = stockTrackedProduct();
    stockSeed($product, OperationalSite::factory()->create(), '4.000');
    $untracked = Product::factory()->create();

    Sanctum::actingAs(stockProductActor(['viewAny']));
    $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.is_stock_tracked', true)->assertJsonPath('data.stock_quantity', '4.000');
    $this->getJson("/api/products/{$untracked->id}")->assertOk()->assertJsonPath('data.is_stock_tracked', false)->assertJsonPath('data.stock_quantity', null);

    Sanctum::actingAs(stockProductActor());
    $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.is_stock_tracked', true)->assertJsonPath('data.stock_quantity', null);
});

it('0307 AC-013: a product or site with movements cannot be deleted, a user neither', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    $author = User::factory()->create();
    StockMovement::factory()->create(['product_id' => $product->id, 'to_operational_site_id' => $site->id, 'created_by' => $author->id]);
    $free = Product::factory()->create();
    $freeSite = OperationalSite::factory()->create();

    $admin = stockProductActor();
    foreach (['operational-sites', 'users'] as $resource) {
        foreach (['delete', 'viewAny'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
            $admin->givePermissionTo("{$resource}.{$ability}");
        }
    }
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/products/{$product->id}")->assertStatus(409)->assertJsonPath('success', false);
    $this->deleteJson("/api/operational-sites/{$site->id}")->assertStatus(409);
    $this->deleteJson("/api/users/{$author->id}")->assertStatus(409);
    expect(Product::find($product->id))->not->toBeNull()
        ->and(OperationalSite::find($site->id))->not->toBeNull()
        ->and(User::find($author->id))->not->toBeNull();

    $this->deleteJson("/api/products/{$free->id}")->assertNoContent();
    $this->deleteJson("/api/operational-sites/{$freeSite->id}")->assertNoContent();

    $bulk = $this->postJson('/api/tables/products/bulk-delete', ['ids' => [$product->id]])->assertOk();
    expect(Product::find($product->id))->not->toBeNull();
});
