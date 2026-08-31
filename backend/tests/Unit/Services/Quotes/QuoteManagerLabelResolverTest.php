<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Quotes\QuoteManagerLabelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Spec 0087, D-8: the gemello of OpportunityManagerLabelResolver (AC-020..
// 023 there), resolving categories from the Offerta's own REVENUE lines'
// products instead of an Opportunity's product lines — quote_lines carries
// no product_category_id of its own (spec 0065 D-7). FALLS BACK to the
// Opportunity's own productLines when the Offerta has no revenue line yet
// (the create-time prefill window, D-5) — the unicity rule applies WITHIN
// each of the two sources, never ACROSS them.

uses(TestCase::class, RefreshDatabase::class);

it('a single revenue line resolves to that category\'s effective labels', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    QuoteLine::factory()->for($quote)->create(['product_id' => $product->id]);
    $quote->load('offerLines.product.category');

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))
        ->toBe(['1' => 'Commerciale', '2' => 'Operatore']);
});

it('two revenue lines resolving to DIFFERENT labels -> []', function (): void {
    $categoryA = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $categoryB = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Consulente']]);
    $quote = Quote::factory()->create();
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryA->id])->id]);
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryB->id])->id]);
    $quote->load('offerLines.product.category');

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))->toBe([]);
});

it('two DIFFERENT categories resolving to IDENTICAL labels are not a conflict', function (): void {
    $categoryA = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $categoryB = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $quote = Quote::factory()->create();
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryA->id])->id]);
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryB->id])->id]);
    $quote->load('offerLines.product.category');

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))->toBe(['2' => 'Operatore']);
});

it('a quote with no revenue line and an opportunity with no product line -> []', function (): void {
    $quote = Quote::factory()->create();
    $quote->load(['offerLines.product.category', 'opportunity.productLines.productCategory']);

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))->toBe([]);
});

// ---------------------------------------------------------------------------
// D-8 fallback: no revenue line yet -> the Opportunity's own productLines
// ---------------------------------------------------------------------------

it('D-8 fallback: a quote with NO revenue line falls back to its opportunity\'s product-line labels', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->load(['offerLines.product.category', 'opportunity.productLines.productCategory']);

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))
        ->toBe(['1' => 'Commerciale', '2' => 'Operatore']);
});

it('D-8 fallback: once the quote HAS a revenue line, its own products win over the opportunity', function (): void {
    $opportunityCategory = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Dal Deal']]);
    $lineCategory = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Dalla Riga']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $opportunityCategory->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $lineCategory->id])->id]);
    $quote->load(['offerLines.product.category', 'opportunity.productLines.productCategory']);

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))->toBe(['1' => 'Dalla Riga']);
});

it('D-8 fallback: the unicity rule applies WITHIN the opportunity source too — disagreeing product lines -> []', function (): void {
    $categoryA = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $categoryB = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Consulente']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->load(['offerLines.product.category', 'opportunity.productLines.productCategory']);

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))->toBe([]);
});

it('a COST line does not influence the resolution: only REVENUE lines count', function (): void {
    $revenueCategory = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale']]);
    $costCategory = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutt\'altro']]);
    $quote = Quote::factory()->create();
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $revenueCategory->id])->id]);
    QuoteLine::factory()->cost()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $costCategory->id])->id]);
    $quote->load('offerLines.product.category');

    expect(app(QuoteManagerLabelResolver::class)->resolve($quote))->toBe(['1' => 'Commerciale']);
});
