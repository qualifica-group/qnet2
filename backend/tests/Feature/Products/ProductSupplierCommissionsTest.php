<?php

declare(strict_types=1);

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\SupplierCommissionDirection;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\ProductTypology;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0320 rev.3: D-21 (the commission a supplier carries on a product),
 * D-22 (cost 0 + `commission` in the detail). AC-028, AC-029.
 */

/** @return array{0: Product, 1: Registry, 2: ProductCategory} */
function supComProduct(bool $enabled = true): array
{
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($parent)->create();
    $product = Product::factory()->create([
        'category_id' => $child->id,
        'product_typology_id' => ($enabled ? ProductTypology::factory()->supplierCommission() : ProductTypology::factory())->create()->id,
    ]);

    return [$product, Registry::factory()->supplier()->create(), $parent];
}

/** @param  array<string, mixed>  $attributes */
function supComRule(array $attributes): CommissionConfiguration
{
    return CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Supplier,
        'value' => 7,
        ...$attributes,
    ]);
}

function supComDedicatedRule(Product $product, Registry $supplier, array $attributes = []): CommissionConfiguration
{
    return supComRule([
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'recipient_type' => 'registry',
        'recipient_id' => $supplier->id,
        ...$attributes,
    ]);
}

function supComQuery(array $params): string
{
    return '/api/products/supplier-commissions?'.http_build_query($params);
}

beforeEach(fn () => Sanctum::actingAs(productUserWith(['viewAny', 'create', 'update', 'view'])));

it('AC-028: a rule dedicated to the supplier on the product is returned', function () {
    [$product, $supplier] = supComProduct();
    $rule = supComDedicatedRule($product, $supplier, ['value' => 12.5, 'supplier_commission_direction' => SupplierCommissionDirection::Received]);

    $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => [$supplier->id]]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.supplier_id', $supplier->id)
        ->assertJsonPath('data.0.commission.configuration_id', $rule->id)
        ->assertJsonPath('data.0.commission.type', 'PERCENTAGE')
        ->assertJsonPath('data.0.commission.direction', 'RECEIVED')
        ->assertJsonPath('data.0.commission.value', '12.5000');
});

it('AC-028: only a generic Supplier rule on the parent category applies, and no rule means null', function () {
    [$product, $supplier, $parent] = supComProduct();
    $other = Registry::factory()->supplier()->create();

    $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => [$supplier->id]]))
        ->assertOk()->assertJsonPath('data.0.commission', null);

    $rule = supComRule([
        'application_scope' => CommissionApplicationScope::ProductCategory,
        'product_category_id' => $parent->id,
        'product_id' => null,
    ]);

    $response = $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => [$supplier->id, $other->id]]))->assertOk();
    expect($response->json('data.0.commission.configuration_id'))->toBe($rule->id)
        ->and($response->json('data.1.commission.configuration_id'))->toBe($rule->id);
});

it('AC-028: a product being created resolves through its category and typology', function () {
    [$product, $supplier, $parent] = supComProduct(enabled: false);
    $rule = supComRule([
        'application_scope' => CommissionApplicationScope::ProductCategory,
        'product_category_id' => $parent->id,
        'product_id' => null,
    ]);
    $enabled = ProductTypology::factory()->supplierCommission()->create();
    $disabled = ProductTypology::factory()->create();

    $this->getJson(supComQuery(['category_id' => $product->category_id, 'product_typology_id' => $enabled->id, 'supplier_ids' => [$supplier->id]]))
        ->assertOk()->assertJsonPath('data.0.commission.configuration_id', $rule->id);

    $this->getJson(supComQuery(['category_id' => $product->category_id, 'product_typology_id' => $disabled->id, 'supplier_ids' => [$supplier->id]]))
        ->assertOk()->assertJsonPath('data.0.commission', null);
});

it('AC-028: a typology without the Supplier calculation, a suspended or an expired rule give null', function () {
    [$off, $supplierOff] = supComProduct(enabled: false);
    supComDedicatedRule($off, $supplierOff);
    $this->getJson(supComQuery(['product_id' => $off->id, 'supplier_ids' => [$supplierOff->id]]))
        ->assertOk()->assertJsonPath('data.0.commission', null);

    [$suspended, $supplierSuspended] = supComProduct();
    supComDedicatedRule($suspended, $supplierSuspended, ['status' => CommissionConfigurationStatus::Suspended]);
    $this->getJson(supComQuery(['product_id' => $suspended->id, 'supplier_ids' => [$supplierSuspended->id]]))
        ->assertOk()->assertJsonPath('data.0.commission', null);

    [$expired, $supplierExpired] = supComProduct();
    supComDedicatedRule($expired, $supplierExpired, ['valid_from' => now()->subDays(10)->toDateString(), 'valid_until' => now()->subDay()->toDateString()]);
    $this->getJson(supComQuery(['product_id' => $expired->id, 'supplier_ids' => [$supplierExpired->id]]))
        ->assertOk()->assertJsonPath('data.0.commission', null);
});

it('AC-028: users with no products, quotes or commessa-cost permission get 403', function () {
    [$product, $supplier] = supComProduct();
    // The Gate throws on a permission missing from the catalogue, so they must all exist.
    foreach (['quotes.create', 'quotes.update', 'work-orders.manageCosts'] as $permission) {
        // beforeEach's actingAs() switched the default guard, so the guard is explicit.
        Permission::findOrCreate($permission, 'web');
    }
    $quoteEditor = User::factory()->create();
    $quoteEditor->givePermissionTo('quotes.update');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => [$supplier->id]]))->assertForbidden();

    Sanctum::actingAs($quoteEditor);
    $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => [$supplier->id]]))->assertOk();
});

it('AC-028: invalid parameters are 422', function () {
    [$product, $supplier] = supComProduct();
    $notSupplier = Registry::factory()->create(['is_supplier' => false]);

    $this->getJson(supComQuery(['supplier_ids' => [$supplier->id]]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
    $this->getJson(supComQuery(['product_id' => $product->id, 'category_id' => $product->category_id, 'supplier_ids' => [$supplier->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('product_id');
    $this->getJson(supComQuery(['product_id' => 999999, 'supplier_ids' => [$supplier->id]]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
    $this->getJson(supComQuery(['product_id' => $product->id]))->assertUnprocessable()->assertJsonValidationErrors('supplier_ids');
    $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => [$notSupplier->id]]))->assertUnprocessable()->assertJsonValidationErrors('supplier_ids.0');
    $this->getJson(supComQuery(['product_id' => $product->id, 'supplier_ids' => range(1, 51)]))->assertUnprocessable()->assertJsonValidationErrors('supplier_ids');
});

it('AC-029: a supplier with a commission is saved with cost 0 and the detail exposes the commission', function () {
    [$product, $supplier] = supComProduct();
    $free = Registry::factory()->supplier()->create();
    $rule = supComDedicatedRule($product, $supplier);

    $response = $this->patchJson("/api/products/{$product->id}", ['suppliers' => [
        ['supplier_id' => $supplier->id, 'price' => 80, 'cost' => 50, 'is_default' => true],
        ['supplier_id' => $free->id, 'price' => 90, 'cost' => 40, 'is_default' => false],
    ]])->assertOk();

    $byId = collect($response->json('data.suppliers'))->keyBy('supplier_id');
    expect($byId[$supplier->id]['cost'])->toBe('0.00')
        ->and($byId[$supplier->id]['price'])->toBe('80.00')
        ->and($byId[$supplier->id]['commission']['configuration_id'])->toBe($rule->id)
        ->and($byId[$free->id]['cost'])->toBe('40.00')
        ->and($byId[$free->id]['commission'])->toBeNull();
    expect(ProductSupplier::where('product_id', $product->id)->where('supplier_id', $supplier->id)->value('cost'))->toBe('0.00');

    $this->getJson("/api/products/{$product->id}")->assertOk()
        ->assertJsonPath('data.suppliers.0.commission.configuration_id', $rule->id);
});

it('AC-029: the for-select keeps the plain supplier shape without commission', function () {
    [$product, $supplier] = supComProduct();
    supComDedicatedRule($product, $supplier);
    ProductSupplier::factory()->default()->create(['product_id' => $product->id, 'supplier_id' => $supplier->id, 'cost' => 0]);

    $row = collect($this->getJson('/api/products/for-select?ids[]='.$product->id)->assertOk()->json('items'))->first();

    expect($row['meta']['suppliers'][0])->not->toHaveKey('commission');
});

it('AC-029: with the typology calculation off the cost is kept', function () {
    [$product, $supplier] = supComProduct(enabled: false);
    supComDedicatedRule($product, $supplier);

    $this->patchJson("/api/products/{$product->id}", ['suppliers' => [['supplier_id' => $supplier->id, 'cost' => 50]]])
        ->assertOk()
        ->assertJsonPath('data.suppliers.0.cost', '50.00')
        ->assertJsonPath('data.suppliers.0.commission', null);
});
