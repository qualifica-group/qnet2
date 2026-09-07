<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0102, AC-001/002/004/005/006: at least one REVENUE line is mandatory
 * on the Offerte channel — on every POST, and on an explicit PATCH
 * svuotamento — while an update that never submits `offer_lines` leaves a
 * historic zero-line Offerta saveable on its other fields (D-2). Exercised
 * over real HTTP, where the rule lives
 * (ValidatesQuoteLines::requireOfferLineOnCreate/requireOfferLineOnUpdate).
 */
uses(RefreshDatabase::class);

if (! function_exists('offerLineRequirementActor')) {
    function offerLineRequirementActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update']);

        return $user;
    }
}

if (! function_exists('offerLineRequirementNewStatus')) {
    function offerLineRequirementNewStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('offerLineRequirementProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (D-7), so a REVENUE line never trips the 422 coverage guard
     * (OpportunityProductLineCoverage) — QuoteCrudHttpTest's own
     * `quoteHttpRevenueProduct()` twin.
     */
    function offerLineRequirementProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

it('AC-001: POST without the offer_lines key is rejected, no quote persisted', function () {
    offerLineRequirementNewStatus();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(offerLineRequirementActor());

    $this->postJson('/api/quotes', ['title' => 'Offerta', 'opportunity_id' => $opportunity->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect(Quote::count())->toBe(0);
});

it('AC-002: POST with offer_lines: [] is rejected, no quote persisted', function () {
    offerLineRequirementNewStatus();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(offerLineRequirementActor());

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect(Quote::count())->toBe(0);
});

it('AC-004: PATCH emptying offer_lines on a two-line quote is rejected, the two rows survive', function () {
    offerLineRequirementNewStatus();
    $opportunity = Opportunity::factory()->create();
    $productA = offerLineRequirementProduct();
    $productB = offerLineRequirementProduct();
    Sanctum::actingAs(offerLineRequirementActor());

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta con due righe',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 20],
        ],
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", ['offer_lines' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect(Quote::find($quoteId)->offerLines()->count())->toBe(2);
});

it('AC-005: PATCH with only title on a historic zero-line quote is saveable (D-2 grandfathering)', function () {
    $quote = Quote::factory()->create(['title' => 'Offerta storica']);
    Sanctum::actingAs(offerLineRequirementActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Offerta storica rinominata'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Offerta storica rinominata');

    expect(Quote::find($quote->id)->title)->toBe('Offerta storica rinominata')
        ->and(Quote::find($quote->id)->offerLines()->count())->toBe(0);
});

it('AC-006: PATCH with a valid REVENUE line on a historic zero-line quote is accepted', function () {
    $quote = Quote::factory()->create();
    $product = offerLineRequirementProduct();
    Sanctum::actingAs(offerLineRequirementActor());

    $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertOk();

    expect(Quote::find($quote->id)->offerLines()->count())->toBe(1);
});
