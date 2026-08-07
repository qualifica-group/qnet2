<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0077, user directive 2026-08-07: an opportunity managed on a `single`
 * product category carries ONE product line (INV-3) — and its offer carries
 * ONE offer (revenue) row. Cost lines are deliberately NOT limited: they are
 * internal cost items, the same asymmetry the coverage rule already has
 * (D-7). Exercised over real HTTP, where the rule lives
 * (ValidatesQuoteLines::enforceSingleOfferLine).
 */
uses(RefreshDatabase::class);

if (! function_exists('singleOfferLineActor')) {
    function singleOfferLineActor(): User
    {
        foreach (['create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.create', 'quotes.update']);

        QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

        Sanctum::actingAs($user);

        return $user;
    }
}

if (! function_exists('singleOfferLineOpportunity')) {
    /**
     * An opportunity whose one covered category resolves to $mode, plus two
     * products sitting INSIDE that category — so a rejection can only come
     * from the row count, never from the coverage rule.
     *
     * @return array{0: Opportunity, 1: Product, 2: Product}
     */
    function singleOfferLineOpportunity(CategoryManagementMode $mode): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'management_mode' => $mode,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return [
            $opportunity,
            Product::factory()->create(['category_id' => $category->id]),
            Product::factory()->create(['category_id' => $category->id]),
        ];
    }
}

if (! function_exists('singleOfferLineRow')) {
    /**
     * @return array<string, mixed>
     */
    function singleOfferLineRow(Product $product): array
    {
        return ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10];
    }
}

it('POST with two offer lines on a single-mode opportunity is rejected, naming offer_lines', function () {
    singleOfferLineActor();
    [$opportunity, $productA, $productB] = singleOfferLineOpportunity(CategoryManagementMode::Single);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta a riga singola',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA), singleOfferLineRow($productB)],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['offer_lines']);

    expect(Quote::count())->toBe(0);
});

it('POST with a single offer line on a single-mode opportunity is accepted', function () {
    singleOfferLineActor();
    [$opportunity, $productA] = singleOfferLineOpportunity(CategoryManagementMode::Single);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta a riga singola',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA)],
    ])->assertCreated();

    expect(Quote::count())->toBe(1);
});

it('a single-mode opportunity still accepts several COST lines: only the offer is limited', function () {
    singleOfferLineActor();
    [$opportunity, $productA, $productB] = singleOfferLineOpportunity(CategoryManagementMode::Single);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta a riga singola',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA)],
        'cost_lines' => [singleOfferLineRow($productA), singleOfferLineRow($productB)],
    ])->assertCreated();

    expect(Quote::sole()->costLines()->count())->toBe(2);
});

it('POST with two offer lines on a multiple-mode opportunity is untouched', function () {
    singleOfferLineActor();
    [$opportunity, $productA, $productB] = singleOfferLineOpportunity(CategoryManagementMode::Multiple);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta multi riga',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA), singleOfferLineRow($productB)],
    ])->assertCreated();

    expect(Quote::sole()->offerLines()->count())->toBe(2);
});

it('POST with two offer lines on an opportunity with no product line yet is untouched (indeterminate mode)', function () {
    singleOfferLineActor();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'management_mode' => CategoryManagementMode::Multiple,
    ]);
    $opportunity = Opportunity::factory()->create();
    $productA = Product::factory()->create(['category_id' => $category->id]);
    $productB = Product::factory()->create(['category_id' => $category->id]);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta senza copertura',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA), singleOfferLineRow($productB)],
    ])->assertCreated();

    expect(Quote::sole()->offerLines()->count())->toBe(2);
});

it('PATCH growing a single-mode quote to two offer lines is rejected', function () {
    singleOfferLineActor();
    [$opportunity, $productA, $productB] = singleOfferLineOpportunity(CategoryManagementMode::Single);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta a riga singola',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA)],
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [singleOfferLineRow($productA), singleOfferLineRow($productB)],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['offer_lines']);

    expect(Quote::find($quoteId)->offerLines()->count())->toBe(1);
});

it('PATCH that does not submit offer_lines leaves a historic non-conforming quote saveable', function () {
    singleOfferLineActor();
    [$opportunity, $productA, $productB] = singleOfferLineOpportunity(CategoryManagementMode::Multiple);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta storica',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [singleOfferLineRow($productA), singleOfferLineRow($productB)],
    ])->assertCreated()->json('data.id');

    // The category is switched to `single` AFTER the fact: the two rows the
    // quote already carries are grandfathered as long as they are not
    // resubmitted (D-5's rule, applied to the offer).
    ProductCategory::query()->update(['management_mode' => CategoryManagementMode::Single]);

    $this->patchJson("/api/quotes/{$quoteId}", ['title' => 'Offerta storica rinominata'])->assertOk();

    expect(Quote::find($quoteId)->offerLines()->count())->toBe(2);
});

it('exposes the rejection message as the translatable source string', function () {
    expect(OpportunityProductLineCoverage::SINGLE_OFFER_LINE_MESSAGE)
        ->toBe('This opportunity is managed on a single product category: its offer may carry one product row only.');
});
