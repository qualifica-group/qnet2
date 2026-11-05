<?php

use App\Jobs\ClassifyRevenueLinesJob;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-007: the mass action updates the products and queues the job; the job spares classified lines', function () {
    Queue::fake();
    $type = revenueSubAccount();
    $manual = revenueSubAccount();
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $open = invoiceLineOf($product->id);
    $classified = invoiceLineOf($product->id, false, ['revenue_type_id' => $manual->id]);
    $untouched = invoiceLineOf($other->id);
    Sanctum::actingAs(productUserWith(['update']));

    $this->postJson('/api/products/revenue-type', ['ids' => [$product->id], 'revenue_type_id' => $type->id])
        ->assertOk()->assertJsonPath('data.updated', 1);

    expect($product->fresh()->revenue_type_id)->toBe($type->id);
    Queue::assertPushed(ClassifyRevenueLinesJob::class);

    app()->call([new ClassifyRevenueLinesJob([$product->id]), 'handle']);

    expect($open->fresh()->revenue_type_id)->toBe($type->id)
        ->and($classified->fresh()->revenue_type_id)->toBe($manual->id)
        ->and($untouched->fresh()->revenue_type_id)->toBeNull();
});

it('AC-007: the mass action is 403 without products.update, 422 on bad payload, and queues nothing when unchanged', function () {
    Queue::fake();
    $type = revenueSubAccount();
    $product = Product::factory()->create(['revenue_type_id' => $type->id]);

    Sanctum::actingAs(productUserWith(['view']));
    $this->postJson('/api/products/revenue-type', ['ids' => [$product->id], 'revenue_type_id' => null])->assertForbidden();

    Sanctum::actingAs(productUserWith(['update']));
    $this->postJson('/api/products/revenue-type', ['ids' => [], 'revenue_type_id' => $type->id])->assertUnprocessable();
    $this->postJson('/api/products/revenue-type', ['ids' => [$product->id]])->assertUnprocessable()->assertJsonValidationErrors('revenue_type_id');
    $this->postJson('/api/products/revenue-type', ['ids' => [$product->id], 'revenue_type_id' => revenueSubAccount(['is_active' => false])->id])->assertUnprocessable();

    $this->postJson('/api/products/revenue-type', ['ids' => [$product->id], 'revenue_type_id' => $type->id])->assertOk();
    Queue::assertNothingPushed();
});

it('AC-008: changing a category queues the job, which classifies only the inheriting products', function () {
    Queue::fake();
    $type = revenueSubAccount();
    $own = revenueSubAccount();
    $category = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $category->id]);
    $inheriting = Product::factory()->create(['category_id' => $child->id]);
    $withOwn = Product::factory()->create(['category_id' => $child->id, 'revenue_type_id' => $own->id]);
    $outside = Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id]);
    $lines = [invoiceLineOf($inheriting->id), invoiceLineOf($withOwn->id), invoiceLineOf($outside->id)];
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->patchJson("/api/product-categories/{$category->id}", ['revenue_type_id' => $type->id])->assertOk();

    Queue::assertPushed(ClassifyRevenueLinesJob::class);
    app()->call([new ClassifyRevenueLinesJob(categoryId: $category->id), 'handle']);

    expect($lines[0]->fresh()->revenue_type_id)->toBe($type->id)
        ->and($lines[1]->fresh()->revenue_type_id)->toBeNull()
        ->and($lines[2]->fresh()->revenue_type_id)->toBeNull();
});

it('AC-008: a product update that changes the sub-account queues the job', function () {
    Queue::fake();
    $product = Product::factory()->create();
    Sanctum::actingAs(productUserWith(['view', 'update']));

    $this->patchJson("/api/products/{$product->id}", ['revenue_type_id' => revenueSubAccount()->id])->assertOk();

    Queue::assertPushed(ClassifyRevenueLinesJob::class);
});

it('AC-009: the command classifies every classifiable NULL line and is idempotent', function () {
    $default = institutionCommissionDefault();
    $type = revenueSubAccount();
    $product = Product::factory()->create(['revenue_type_id' => $type->id]);
    $sale = invoiceLineOf($product->id);
    $ente = invoiceLineOf($product->id, true);
    $orphan = invoiceLineOf(null);

    $this->artisan('chart:classify-revenue')->expectsOutput('2 line(s) classified.')->assertSuccessful();
    $this->artisan('chart:classify-revenue')->expectsOutput('0 line(s) classified.')->assertSuccessful();

    expect($sale->fresh()->revenue_type_id)->toBe($type->id)
        ->and($ente->fresh()->revenue_type_id)->toBe($default->id)
        ->and($orphan->fresh()->revenue_type_id)->toBeNull();
});
