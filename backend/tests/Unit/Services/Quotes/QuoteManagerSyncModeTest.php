<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Services\Quotes\QuoteManagerSyncMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Spec 0087, D-7: an Opportunity is "Gestori Account sincronizzati" only
// when BOTH independent category settings agree — the AND this class is the
// one place that builds. Covers the 2x2 matrix.

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('syncModeOpportunity')) {
    function syncModeOpportunity(bool $singleQuote, CategoryManagementMode $mode): Opportunity
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'single_quote_per_opportunity' => $singleQuote,
            'management_mode' => $mode,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

it('is synchronized when both settings agree (single quote + single management)', function () {
    $opportunity = syncModeOpportunity(true, CategoryManagementMode::Single);

    expect(app(QuoteManagerSyncMode::class)->isSynchronized($opportunity))->toBeTrue();
});

it('is NOT synchronized when only single_quote_per_opportunity is set', function () {
    $opportunity = syncModeOpportunity(true, CategoryManagementMode::Multiple);

    expect(app(QuoteManagerSyncMode::class)->isSynchronized($opportunity))->toBeFalse();
});

it('is NOT synchronized when only management_mode is single', function () {
    $opportunity = syncModeOpportunity(false, CategoryManagementMode::Single);

    expect(app(QuoteManagerSyncMode::class)->isSynchronized($opportunity))->toBeFalse();
});

it('is NOT synchronized when neither setting is set', function () {
    $opportunity = syncModeOpportunity(false, CategoryManagementMode::Multiple);

    expect(app(QuoteManagerSyncMode::class)->isSynchronized($opportunity))->toBeFalse();
});

it('is NOT synchronized when the opportunity has no product line', function () {
    $opportunity = Opportunity::factory()->create();

    expect(app(QuoteManagerSyncMode::class)->isSynchronized($opportunity))->toBeFalse();
});
