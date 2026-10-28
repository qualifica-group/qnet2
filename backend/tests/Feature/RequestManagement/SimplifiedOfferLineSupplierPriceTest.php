<?php

declare(strict_types=1);

use App\Enums\CategoryManagementMode;
use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\Quotes\ProductOfferLineResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0320 rev. 2, D-16/D-17, AC-022: the price frozen on a simplified offer
// line and the one proposed by the Lead conversion come from the supplier of
// the line (the default one for the conversion), else from the product.
// Self-contained: parallel workers load one file each.

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: ProductCategory, 2: Product, 3: Registry, 4: Registry}
 *                                                                                  operator, simplified category, product (standard price 150) with default supplier A (price 90) and B (price 70)
 */
function supplierPriceFixture(): array
{
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.create', 'request-management.viewAll']);

    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'management_mode' => CategoryManagementMode::Multiple,
        'simplified_offer_line' => true,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 150]);
    $a = Registry::factory()->supplier()->create();
    $b = Registry::factory()->supplier()->create();
    ProductSupplier::factory()->for($product)->default()->create(['supplier_id' => $a->id, 'price' => 90]);
    ProductSupplier::factory()->for($product)->create(['supplier_id' => $b->id, 'price' => 70]);

    return [$actor, $category, $product, $a, $b];
}

function supplierPriceRequest(User $operator, ProductCategory $category): Quote
{
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
}

it('AC-022: a new simplified row freezes the price of its supplier, else the standard price', function () {
    [$actor, $category, $product, , $b] = supplierPriceFixture();
    $plain = Product::factory()->create(['category_id' => $category->id, 'price' => 33]);
    $quote = supplierPriceRequest($actor, $category);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['offer_lines' => [
        ['product_id' => $product->id, 'supplier_id' => $b->id, 'quantity' => 4, 'unit_price' => 1],
        ['product_id' => $plain->id, 'quantity' => 4, 'unit_price' => 1],
    ]])->assertOk();

    $lines = $quote->offerLines()->get()->keyBy('product_id');
    expect((float) $lines[$product->id]->unit_price)->toBe(70.0)
        ->and($lines[$product->id]->supplier_id)->toBe($b->id)
        ->and((float) $lines[$plain->id]->unit_price)->toBe(33.0);
});

it('AC-022: creation freezes the price of the submitted supplier', function () {
    [$actor, $category, $product, , $b] = supplierPriceFixture();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id]],
        'offer_lines' => [['product_id' => $product->id, 'supplier_id' => $b->id, 'quantity' => 2, 'unit_price' => 999]],
    ])->assertCreated();

    $line = Quote::query()->sole()->offerLines()->sole();
    expect((float) $line->unit_price)->toBe(70.0)->and($line->supplier_id)->toBe($b->id);
});

it('AC-022: changing the supplier of an existing row takes the new supplier price; an untouched row keeps its frozen one', function () {
    [$actor, $category, $product, $a, $b] = supplierPriceFixture();
    $quote = supplierPriceRequest($actor, $category);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id, 'product_id' => $product->id, 'supplier_id' => $a->id,
        'line_type' => QuoteLineType::Revenue, 'quantity' => 1, 'unit_price' => 55,
    ]);
    Sanctum::actingAs($actor);

    // Same supplier, supplier omitted: the stored (grandfathered) price stays.
    $this->patchJson("/api/request-management/{$quote->id}", ['offer_lines' => [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]]])->assertOk();
    expect((float) $line->refresh()->unit_price)->toBe(55.0);

    $this->patchJson("/api/request-management/{$quote->id}", ['offer_lines' => [['id' => $line->id, 'product_id' => $product->id, 'supplier_id' => $a->id, 'quantity' => 1, 'unit_price' => 1]]])->assertOk();
    expect((float) $line->refresh()->unit_price)->toBe(55.0);

    $this->patchJson("/api/request-management/{$quote->id}", ['offer_lines' => [['id' => $line->id, 'product_id' => $product->id, 'supplier_id' => $b->id, 'quantity' => 1, 'unit_price' => 1]]])->assertOk();
    expect((float) $line->refresh()->unit_price)->toBe(70.0)->and($line->supplier_id)->toBe($b->id);
});

it('AC-022: the Lead conversion proposes the default supplier price, else the standard price', function () {
    [, , $product, $a] = supplierPriceFixture();
    $plain = Product::factory()->create(['price' => 33]);

    $lines = collect(app(ProductOfferLineResolver::class)->resolve([$product->id, $plain->id]))->keyBy('productId');

    expect($lines[$product->id]->unitPrice)->toBe(90.0)
        ->and($lines[$product->id]->supplierId)->toBe($a->id)
        ->and($lines[$plain->id]->unitPrice)->toBe(33.0)
        ->and($lines[$plain->id]->supplierId)->toBeNull();
});
