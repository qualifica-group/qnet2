<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * QuoteService's use of the extracted OpportunityProductLineCoverage (spec
 * 0065, D-7/AC-054): a REVENUE quote line whose product sits outside the
 * opportunity's covered categories adds the (business function, category)
 * pair; a COST line never does. AC-054 itself (the pre-existing
 * OpportunityProductsOfInterestTest suite stays green after the extraction)
 * is verified by running that file unmodified — not duplicated here.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteCoverageService')) {
    function quoteCoverageService(): QuoteService
    {
        return app(QuoteService::class);
    }
}

if (! function_exists('quoteCoverageNewStatus')) {
    /**
     * The mandatory "Aperta" (`open`) row of the GLOBAL default workflow set
     * is seeded by the quote_workflow_statuses migration itself (spec
     * 0047, moved onto the Offerta by spec 0083 D-8) — RefreshDatabase
     * already leaves it in place, so tests fetch it rather than creating a
     * second `system_key` row (unique per set).
     */
    function quoteCoverageNewStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('quoteCoverageActor')) {
    function quoteCoverageActor(): User
    {
        return User::factory()->create();
    }
}

if (! function_exists('quoteCoverageData')) {
    /**
     * @param  array<string, mixed>  $overrides
     */
    function quoteCoverageData(int $opportunityId, array $overrides = []): CreateQuoteData
    {
        $defaults = [
            'code' => null,
            'title' => 'Offerta copertura',
            'opportunityId' => $opportunityId,
            'workflowStatusId' => null,
            'note' => null,
            'commercialId' => null,
            'commercialIdSubmitted' => false,
            'reporterId' => null,
            'reporterIdSubmitted' => false,
            'supervisorId' => null,
            'supervisorIdSubmitted' => false,
            'internalNotes' => null,
            'offerLines' => null,
            'costLines' => null,
        ];

        return new CreateQuoteData(...array_merge($defaults, $overrides));
    }
}

if (! function_exists('quoteCoverageCategory')) {
    function quoteCoverageCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

it('AC-050: a REVENUE line whose product category is not covered adds the pair to the opportunity', function () {
    quoteCoverageNewStatus();
    $opportunity = Opportunity::factory()->create();
    $category = quoteCoverageCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);

    quoteCoverageService()->create(quoteCoverageData($opportunity->id, [
        'offerLines' => [new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null)],
    ]), quoteCoverageActor());

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
});

it('AC-051: a category with no effective business function is rejected, nothing persisted', function () {
    quoteCoverageNewStatus();
    $opportunity = Opportunity::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => null]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    expect(fn () => quoteCoverageService()->create(quoteCoverageData($opportunity->id, [
        'offerLines' => [new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null)],
    ]), quoteCoverageActor()))->toThrow(ValidationException::class);

    expect(Quote::count())->toBe(0);
    $this->assertDatabaseMissing('opportunity_product_lines', ['opportunity_id' => $opportunity->id]);
});

it('AC-052: the same outside-category product used in a COST line does not alter the opportunity product lines', function () {
    quoteCoverageNewStatus();
    $opportunity = Opportunity::factory()->create();
    $category = quoteCoverageCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);

    quoteCoverageService()->create(quoteCoverageData($opportunity->id, [
        'costLines' => [new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null)],
    ]), quoteCoverageActor());

    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
});

it('AC-053: a pair already present on the opportunity is not duplicated', function () {
    quoteCoverageNewStatus();
    $category = quoteCoverageCategory();
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $productA = Product::factory()->create(['category_id' => $category->id]);
    $productB = Product::factory()->create(['category_id' => $category->id]);

    quoteCoverageService()->create(quoteCoverageData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $productA->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null),
            new QuoteLineData(productId: $productB->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null),
        ],
    ]), quoteCoverageActor());

    expect($opportunity->productLines()->where('product_category_id', $category->id)->count())->toBe(1);
});
