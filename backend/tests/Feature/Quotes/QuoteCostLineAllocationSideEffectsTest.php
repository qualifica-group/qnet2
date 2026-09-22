<?php

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
 * Spec 0144, D-3: the side effects of a COST line's allocation once it is in
 * place — removing the associated offer line clears it (D-3, `nullOnDelete`,
 * AC-008) on both write channels, a product swap on the SAME revenue row
 * leaves it alone (AC-009), and header totals never move because of it
 * (AC-010, D-6). See QuoteCostLineAllocationTest for the resolution happy
 * paths and QuoteCostLineAllocationRejectionTest for AC-005/006/007.
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
// AC-008 — removing the associated offer line clears the association
// ---------------------------------------------------------------------------

it('AC-008: Offerte PATCH that drops the associated offer line clears the cost association', function () {
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
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_index' => 1],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineAId = $created->json('data.offer_lines.0.id');
    $costLineId = $created->json('data.cost_lines.0.id');
    expect($created->json('data.cost_lines.0.offer_line_id'))->not->toBeNull();

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['id' => $offerLineAId, 'product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertOk();

    expect(QuoteLine::find($costLineId)?->offer_line_id)->toBeNull();
    $this->assertDatabaseHas('quote_lines', ['id' => $costLineId, 'quote_id' => $quoteId]);
});

it('AC-008: Gestione Richieste dropping the associated offer line clears the cost association', function () {
    $actor = costAllocRequestManagementActor();
    $quote = costAllocRequestManagementQuote($actor);
    $productA = costAllocRevenueProduct();
    $productB = costAllocRevenueProduct();

    $offerLineA = QuoteLine::factory()->for($quote)->create(['product_id' => $productA->id, 'sort_order' => 0]);
    $offerLineB = QuoteLine::factory()->for($quote)->create(['product_id' => $productB->id, 'sort_order' => 1]);
    $costLine = QuoteLine::factory()->cost()->for($quote)->create(['offer_line_id' => $offerLineB->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [
            [
                'id' => $offerLineA->id,
                'product_id' => $productA->id,
                'quantity' => (float) $offerLineA->quantity,
                'unit_price' => (float) $offerLineA->unit_price,
            ],
        ],
    ])->assertOk();

    expect($costLine->fresh()->offer_line_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-009 — a product swap on the SAME revenue row keeps costs allocated
// ---------------------------------------------------------------------------

it('AC-009: changing a REVENUE line\'s product keeps its allocated costs associated', function () {
    costAllocNewStatus();
    $product = costAllocRevenueProduct();
    $newProduct = costAllocRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = costAllocUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
        'cost_lines' => [['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_index' => 0]],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $offerLineId = $created->json('data.offer_lines.0.id');
    $costLineId = $created->json('data.cost_lines.0.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['id' => $offerLineId, 'product_id' => $newProduct->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertOk();

    expect(QuoteLine::find($costLineId)?->offer_line_id)->toBe($offerLineId);
});

// ---------------------------------------------------------------------------
// AC-010 — header totals are unaffected by allocating a cost
// ---------------------------------------------------------------------------

it('AC-010: header totals are identical before and after allocating a cost', function () {
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
    $offerLineId = $created->json('data.offer_lines.0.id');
    $costLineId = $created->json('data.cost_lines.0.id');
    $summaryBefore = $created->json('data.summary');

    $updated = $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['id' => $costLineId, 'product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 5, 'offer_line_id' => $offerLineId],
        ],
    ])->assertOk();

    expect($updated->json('data.summary.revenue.net'))->toBe($summaryBefore['revenue']['net'])
        ->and($updated->json('data.summary.cost.net'))->toBe($summaryBefore['cost']['net'])
        ->and($updated->json('data.summary.margin.net'))->toBe($summaryBefore['margin']['net']);
});
