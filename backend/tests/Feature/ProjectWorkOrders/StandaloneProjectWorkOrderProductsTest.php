<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Tables\CategoryTabs\WorkOrderSourceQuotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0431 (D-17): a project work order born without an offer takes products
 * in place of offer lines; their categories bring the flexible fields
 * ("Informazioni aggiuntive"), the workflow set and the category filters.
 */
function standaloneProductActor(): User
{
    $user = workOrderUserWith(['create', 'update', 'view']);
    $user->givePermissionTo(Permission::findOrCreate('project-work-orders.view', 'web'));

    return $user;
}

/** A product whose category (with its business function) carries one work-order flexible field. */
function standaloneProductWithField(string $code): Product
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attribute = Attribute::factory()->create(['code' => $code, 'type' => 'text']);
    $category->attributes()->attach($attribute->id, [
        'is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value,
    ]);

    return Product::factory()->create(['category_id' => $category->id]);
}

it('creates with products, whose category fields apply and are filled at once', function () {
    Sanctum::actingAs(standaloneProductActor());
    $product = standaloneProductWithField('scope');

    // REQUIREMENT CHANGED (D-18): a product is picked within the product lines.
    $response = $this->postJson('/api/project-work-orders', standaloneProjectPayload([
        'product_lines' => [['product_category_id' => $product->category_id]],
        'product_ids' => [$product->id],
        'attribute_values' => ['scope' => 'ISO 9001'],
    ]))->assertCreated();

    $response->assertJsonPath('data.products.0.id', $product->id)
        ->assertJsonPath('data.attribute_values.scope', 'ISO 9001');
    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toContain('scope');
});

it('previews the fields of the chosen products before saving', function () {
    Sanctum::actingAs(standaloneProductActor());
    $product = standaloneProductWithField('scope');

    $response = $this->postJson('/api/work-orders/form-context', ['product_ids' => [$product->id]])->assertOk();

    expect(collect($response->json('data.applicable_attributes'))->pluck('code')->all())->toBe(['scope']);
});

it('refuses an inactive product, and a product twice', function () {
    Sanctum::actingAs(standaloneProductActor());
    $active = Product::factory()->create();
    $inactive = Product::factory()->create(['is_active' => false]);

    $this->postJson('/api/project-work-orders', standaloneProjectPayload(['product_ids' => [$inactive->id]]))
        ->assertStatus(422)->assertJsonValidationErrors('product_ids.0');
    $this->postJson('/api/project-work-orders', standaloneProjectPayload(['product_ids' => [$active->id, $active->id]]))
        ->assertStatus(422)->assertJsonValidationErrors('product_ids.0');
});

it('refuses a product outside the product lines (D-18)', function () {
    Sanctum::actingAs(standaloneProductActor());
    $outside = standaloneProductWithField('scope');

    $this->postJson('/api/project-work-orders', standaloneProjectPayload(['product_ids' => [$outside->id]]))
        ->assertStatus(422)->assertJsonValidationErrors('product_ids');
    expect(WorkOrder::count())->toBe(0);
});

it('refuses to drop the product line a saved product belongs to (D-18)', function () {
    Sanctum::actingAs(standaloneProductActor());
    $workOrder = standaloneProjectWorkOrder();
    $line = $workOrder->productLines->first();
    $product = Product::factory()->create(['category_id' => $line->product_category_id]);
    $workOrder->products()->attach($product->id);
    $other = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['product_lines' => [['product_category_id' => $other->id]]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids');
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['product_lines' => [['product_category_id' => $other->id]], 'product_ids' => []])
        ->assertOk();
});

it('replaces the products from the detail, keeping one that became inactive', function () {
    Sanctum::actingAs(standaloneProductActor());
    $workOrder = standaloneProjectWorkOrder();
    $kept = Product::factory()->create(['category_id' => $workOrder->productLines->first()->product_category_id]);
    $workOrder->products()->attach($kept->id);
    $kept->update(['is_active' => false]);
    $added = standaloneProductWithField('scope');

    // REQUIREMENT CHANGED (D-18): the added product comes with its product line.
    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'product_lines' => [
            ['product_category_id' => $kept->category_id],
            ['product_category_id' => $added->category_id],
        ],
        'product_ids' => [$kept->id, $added->id],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.products');

    expect(collect($this->getJson("/api/work-orders/{$workOrder->id}")->json('data.applicable_attributes'))->pluck('code')->all())
        ->toContain('scope');
});

it('refuses products on a work order born from an offer: its offer lines decide', function () {
    Sanctum::actingAs(standaloneProductActor());
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['product_ids' => [Product::factory()->create()->id]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids');
});

it('belongs to the categories of its products', function () {
    $workOrder = standaloneProjectWorkOrder();
    $product = Product::factory()->create();
    $workOrder->products()->attach($product->id);

    $ids = WorkOrder::query()->whereIn('id', WorkOrderSourceQuotes::withRevenueCategory([$product->category_id]))->pluck('id')->all();

    expect($ids)->toBe([$workOrder->id]);
});
