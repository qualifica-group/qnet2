<?php

use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0208 (AC-013): a product whose category is EFFECTIVELY inactive (own
 * flag false, or a descendant of an inactive one) cannot be put on a NEW
 * offer/cost line; the products already on the quote stay valid (D-2).
 */
uses(RefreshDatabase::class);

/** A product under a category made inactive through its (inactive) parent. */
function productUnderInactiveBranch(): Product
{
    $branch = ProductCategory::factory()->create(['is_active' => false]);
    $child = ProductCategory::factory()->childOf($branch)->create();

    return Product::factory()->create(['category_id' => $child->id]);
}

it('AC-013: POST rejects an offer line on a product of an inactive category with ONE message', function () {
    Sanctum::actingAs(quoteTableUserWith(['create']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => productUnderInactiveBranch()->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.product_id');

    expect($response->json('errors')['offer_lines.0.product_id'])
        ->toBe([__('This product belongs to an inactive category.')]);
});

it('AC-013: POST rejects a cost line on a product of an inactive category', function () {
    Sanctum::actingAs(quoteTableUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => quoteHttpRevenueProduct()->id, 'quantity' => 1, 'unit_price' => 10]],
        'cost_lines' => [['product_id' => productUnderInactiveBranch()->id, 'quantity' => 1, 'unit_price' => 5]],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.product_id');
});

it('AC-013: PATCH resubmitting the quote OWN products passes after their category is deactivated, a NEW one does not', function () {
    Sanctum::actingAs(quoteTableUserWith(['create', 'update', 'view']));
    $kept = quoteHttpRevenueProduct();
    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $kept->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data.id');

    ProductCategory::query()->whereKey($kept->category_id)->update(['is_active' => false]);

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['product_id' => $kept->id, 'quantity' => 2, 'unit_price' => 10]],
    ])->assertOk();

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['product_id' => $kept->id, 'quantity' => 2, 'unit_price' => 10],
            ['product_id' => productUnderInactiveBranch()->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.1.product_id');
});
