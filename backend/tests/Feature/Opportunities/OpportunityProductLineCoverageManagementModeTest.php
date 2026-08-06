<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * Spec 0077 D-6/AC-020/AC-021: OpportunityProductLineCoverage::ensure() stops
 * auto-adding a missing `opportunity_product_lines` row when the opportunity's
 * resolved management mode is `single`, and keeps auto-adding it otherwise.
 * Exercised through BOTH the extracted service directly and QuoteService (its
 * actual production caller), matching QuoteCoverageTest's existing style.
 */
uses(RefreshDatabase::class);

if (! function_exists('managementModeNewStatus')) {
    function managementModeNewStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('managementModeActor')) {
    function managementModeActor(): User
    {
        return User::factory()->create();
    }
}

if (! function_exists('managementModeCategory')) {
    function managementModeCategory(CategoryManagementMode $mode): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'management_mode' => $mode,
        ]);
    }
}

if (! function_exists('managementModeQuoteData')) {
    function managementModeQuoteData(int $opportunityId, Product $product): CreateQuoteData
    {
        return new CreateQuoteData(
            code: null,
            title: 'Offerta modalita gestione',
            opportunityId: $opportunityId,
            workflowStatusId: null,
            note: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            offerLines: [new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null)],
            costLines: null,
        );
    }
}

it('AC-020: single mode rejects a product outside the covered category, naming it, adding nothing', function () {
    $category = managementModeCategory(CategoryManagementMode::Single);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    $otherCategory = managementModeCategory(CategoryManagementMode::Multiple);
    $product = Product::factory()->create(['category_id' => $otherCategory->id, 'name' => 'Attestato HACCP']);

    try {
        app(OpportunityProductLineCoverage::class)->ensure($opportunity, collect([$product]), 'products_of_interest');
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('products_of_interest');
        expect($exception->errors()['products_of_interest'][0])->toContain('Attestato HACCP');
    }

    expect($opportunity->productLines()->count())->toBe(1);
    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $otherCategory->id,
    ]);
});

it('AC-020: single mode leaves the quote transaction clean, no quote and no row persisted', function () {
    managementModeNewStatus();
    $category = managementModeCategory(CategoryManagementMode::Single);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    $otherCategory = managementModeCategory(CategoryManagementMode::Multiple);
    $product = Product::factory()->create(['category_id' => $otherCategory->id]);

    expect(fn () => app(QuoteService::class)->create(managementModeQuoteData($opportunity->id, $product), managementModeActor()))
        ->toThrow(ValidationException::class);

    expect(Quote::count())->toBe(0);
    expect($opportunity->productLines()->count())->toBe(1);
    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $otherCategory->id,
    ]);
});

it('AC-021: multiple mode keeps auto-adding the missing coverage row, request succeeds', function () {
    managementModeNewStatus();
    $category = managementModeCategory(CategoryManagementMode::Multiple);
    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    $otherCategory = managementModeCategory(CategoryManagementMode::Multiple);
    $product = Product::factory()->create(['category_id' => $otherCategory->id]);

    app(QuoteService::class)->create(managementModeQuoteData($opportunity->id, $product), managementModeActor());

    expect(Quote::count())->toBe(1);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $otherCategory->business_function_id,
        'product_category_id' => $otherCategory->id,
    ]);
});

it('AC-021: indeterminate mode (no existing product line yet) keeps auto-adding, matching the pre-existing default', function () {
    $opportunity = Opportunity::factory()->create();
    $category = managementModeCategory(CategoryManagementMode::Multiple);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $added = app(OpportunityProductLineCoverage::class)->ensure($opportunity, collect([$product]), 'products_of_interest');

    expect($added)->toHaveCount(1);
    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
});
