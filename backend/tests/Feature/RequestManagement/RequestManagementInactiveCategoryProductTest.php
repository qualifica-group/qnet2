<?php

declare(strict_types=1);

use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0208 (AC-013), request-management channels: the work-panel PATCH and
 * the inline `offer_lines` cell both refuse a NEW product of an effectively
 * inactive category, and keep honouring the products already on the quote.
 */
uses(RefreshDatabase::class);

/**
 * @return array{actor: User, quote: Quote, category: ProductCategory}
 */
function inactiveCategoryRequestScenario(): array
{
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.update', 'request-management.viewAll']);

    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    return [
        'actor' => $actor,
        'quote' => Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]),
        'category' => $category,
    ];
}

it('AC-013: PATCH refuses a NEW offer-line product of an inactive category with one message', function () {
    ['actor' => $actor, 'quote' => $quote] = inactiveCategoryRequestScenario();
    $inactive = ProductCategory::factory()->childOf(ProductCategory::factory()->create(['is_active' => false]))->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => Product::factory()->create(['category_id' => $inactive->id])->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.product_id');

    expect($response->json('errors')['offer_lines.0.product_id'])->toBe([__('This product belongs to an inactive category.')])
        ->and($quote->offerLines()->count())->toBe(0);
});

it('AC-013: PATCH resubmitting the quote OWN product passes once its category is deactivated', function () {
    ['actor' => $actor, 'quote' => $quote, 'category' => $category] = inactiveCategoryRequestScenario();
    $product = Product::factory()->create(['category_id' => $category->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id, 'line_type' => QuoteLineType::Revenue]);
    $category->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10]],
    ])->assertOk();
});

it('AC-013: the inline cell refuses a NEW product of an inactive category and accepts the persisted one', function () {
    ['actor' => $actor, 'quote' => $quote, 'category' => $category] = inactiveCategoryRequestScenario();
    $kept = Product::factory()->create(['category_id' => $category->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $kept->id, 'line_type' => QuoteLineType::Revenue]);
    $category->update(['is_active' => false]);
    $other = Product::factory()->create(['category_id' => ProductCategory::factory()->create(['is_active' => false])->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $other->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertStatus(422)->assertJsonValidationErrors('value.0.product_id');

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['product_id' => $kept->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertOk();
});

it('AC-010: PATCH product_lines refuses a NEW inactive category and keeps the persisted line', function () {
    ['actor' => $actor, 'quote' => $quote, 'category' => $category] = inactiveCategoryRequestScenario();
    $inactive = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'is_active' => false,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [['business_function_id' => $inactive->business_function_id, 'product_category_id' => $inactive->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');

    $category->update(['is_active' => false]);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id]],
    ])->assertOk();
});
