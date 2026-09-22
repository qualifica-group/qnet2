<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0144: a COST line's optional imputation to a REVENUE line of the same
 * quote (`quote_lines.offer_line_id`), resolved from `offer_line_id`/
 * `offer_line_index` — the resolution happy paths (AC-002/003/004). AC-001
 * (migration reversibility) lives in QuoteCostLineAllocationMigrationTest;
 * AC-005/006/007 in QuoteCostLineAllocationRejectionTest; AC-008/009/010 in
 * QuoteCostLineAllocationSideEffectsTest; AC-011..017 (frontend) are out of
 * this file's scope.
 */
uses(RefreshDatabase::class);

if (! function_exists('costAllocUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function costAllocUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('costAllocNewStatus')) {
    function costAllocNewStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('costAllocRevenueProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (D-7), so a REVENUE line never trips the 422 coverage guard —
     * mirrors QuoteCrudHttpTest's own `quoteHttpRevenueProduct()`.
     */
    function costAllocRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-002 — create resolves offer_line_index against the SAME request
// ---------------------------------------------------------------------------

it('AC-002: create resolves offer_line_index against the SAME request offer_lines', function () {
    costAllocNewStatus();
    $opportunity = Opportunity::factory()->create();
    $productA = costAllocRevenueProduct();
    $productB = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 20],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_index' => 1],
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 3],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineB = $created->json('data.offer_lines.1.id');

    expect($created->json('data.cost_lines.0.offer_line_id'))->toBe($offerLineB)
        ->and($created->json('data.cost_lines.1.offer_line_id'))->toBeNull();

    $get = $this->getJson("/api/quotes/{$quoteId}")->assertOk();

    expect($get->json('data.cost_lines.0.offer_line_id'))->toBe($offerLineB)
        ->and($get->json('data.cost_lines.1.offer_line_id'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-003 — update with only cost_lines saves it; an absent tab leaves it be
// ---------------------------------------------------------------------------

it('AC-003: update with only cost_lines saves the association; omitting cost_lines leaves it untouched', function () {
    costAllocNewStatus();
    $opportunity = Opportunity::factory()->create();
    $product = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineId = $created->json('data.offer_lines.0.id');
    $costLineId = $created->json('data.cost_lines.0.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['id' => $costLineId, 'product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_id' => $offerLineId],
        ],
    ])->assertOk()->assertJsonPath('data.cost_lines.0.offer_line_id', $offerLineId);

    $this->patchJson("/api/quotes/{$quoteId}", ['title' => 'Offerta rinominata'])
        ->assertOk()
        ->assertJsonPath('data.cost_lines.0.offer_line_id', $offerLineId);
});

// ---------------------------------------------------------------------------
// AC-004 — offer_line_index onto a brand-new offer line submitted alongside
// ---------------------------------------------------------------------------

it('AC-004: update associates a cost to a NEW offer line via offer_line_index', function () {
    costAllocNewStatus();
    $opportunity = Opportunity::factory()->create();
    $existingProduct = costAllocRevenueProduct();
    $newProduct = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $existingProduct->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $existingOfferLineId = $created->json('data.offer_lines.0.id');

    $updated = $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['id' => $existingOfferLineId, 'product_id' => $existingProduct->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $newProduct->id, 'quantity' => 1, 'unit_price' => 20],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_index' => 1],
        ],
    ])->assertOk();

    $newOfferLineId = $updated->json('data.offer_lines.1.id');

    expect($newOfferLineId)->not->toBe($existingOfferLineId)
        ->and($updated->json('data.cost_lines.0.offer_line_id'))->toBe($newOfferLineId);
});
