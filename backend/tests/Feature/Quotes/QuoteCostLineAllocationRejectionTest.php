<?php

use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0144, D-5: the rejection paths of a COST line's `offer_line_id`/
 * `offer_line_index` allocation — an unresolvable reference (AC-005), the
 * mutually-exclusive pair and index bounds (AC-006), and the keys being
 * `prohibited` on `offer_lines.*` on both write channels (AC-007). See
 * QuoteCostLineAllocationTest for the resolution happy paths and
 * QuoteCostLineAllocationSideEffectsTest for AC-008/009/010.
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

if (! function_exists('costAllocRequestManagementActor')) {
    function costAllocRequestManagementActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach (['view', 'update', 'create', 'viewAll'] as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
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

if (! function_exists('costAllocRequestManagementQuote')) {
    /** A request-management Quote the given operator may reach through the panel's own scope. */
    function costAllocRequestManagementQuote(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-005 — an unresolvable offer_line_id is rejected, nothing persisted
// ---------------------------------------------------------------------------

it('AC-005: offer_line_id belonging to ANOTHER quote is rejected, no partial write', function () {
    costAllocNewStatus();
    $product = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data.id');

    $otherOfferLineId = $this->postJson('/api/quotes', [
        'title' => 'Altra offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data.offer_lines.0.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_id' => $otherOfferLineId],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.offer_line_id');

    expect(QuoteLine::where('quote_id', $quoteId)->where('line_type', QuoteLineType::Cost)->count())->toBe(0);
});

it('AC-005: offer_line_id pointing at a COST row is rejected', function () {
    costAllocNewStatus();
    $product = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
        'cost_lines' => [['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5]],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $existingCostLineId = $created->json('data.cost_lines.0.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['id' => $existingCostLineId, 'product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_id' => $existingCostLineId],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.offer_line_id');
});

it('AC-005: offer_line_id removed in the SAME request is rejected, transaction rolled back', function () {
    costAllocNewStatus();
    $productA = costAllocRevenueProduct();
    $productB = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 20],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineAId = $created->json('data.offer_lines.0.id');
    $offerLineBId = $created->json('data.offer_lines.1.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['id' => $offerLineAId, 'product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_id' => $offerLineBId],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.offer_line_id');

    // AC-005: the failed COST resolution rolls back the REVENUE deletion too.
    $this->assertDatabaseHas('quote_lines', ['id' => $offerLineBId]);
    expect(QuoteLine::where('quote_id', $quoteId)->where('line_type', QuoteLineType::Cost)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-006 — offer_line_index bounds and the mutually-exclusive pair
// ---------------------------------------------------------------------------

it('AC-006: offer_line_index without offer_lines in the request is rejected', function () {
    costAllocNewStatus();
    $product = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_index' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.offer_line_index');
});

it('AC-006: offer_line_index out of range is rejected', function () {
    costAllocNewStatus();
    $product = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineId = $created->json('data.offer_lines.0.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['id' => $offerLineId, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_index' => 5],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.offer_line_index');
});

it('AC-006: both offer_line_id and offer_line_index valued is rejected on offer_line_id', function () {
    costAllocNewStatus();
    $product = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineId = $created->json('data.offer_lines.0.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_id' => $offerLineId, 'offer_line_index' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('cost_lines.0.offer_line_id');
});

// ---------------------------------------------------------------------------
// AC-007 — offer_lines.* never accepts the allocation keys, on either channel
// ---------------------------------------------------------------------------

it('AC-007: offer_lines.offer_line_id/offer_line_index are prohibited on Offerte', function () {
    $product = costAllocRevenueProduct();
    $actor = costAllocUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'offer_line_id' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.offer_line_id');

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'offer_line_index' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.offer_line_index');
});

it('AC-007: offer_lines.offer_line_id/offer_line_index are prohibited on Gestione Richieste', function () {
    $actor = costAllocRequestManagementActor();
    $quote = costAllocRequestManagementQuote($actor);
    $product = costAllocRevenueProduct();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'offer_line_id' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.offer_line_id');

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'offer_line_index' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.offer_line_index');
});
