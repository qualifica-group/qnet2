<?php

declare(strict_types=1);

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\QuoteService;
use Spatie\Permission\Models\Permission;

if (! function_exists('revenueLineProduct')) {
    /**
     * A Product under a coherent business-function/category pair (spec 0023
     * REV), with an explicit or freshly factory-made unit of measure —
     * $unitOfMeasure lets a caller freeze/compare a SPECIFIC one.
     */
    function revenueLineProduct(?UnitOfMeasure $unitOfMeasure = null): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'unit_of_measure_id' => $unitOfMeasure?->id ?? UnitOfMeasure::factory()->create()->id,
        ]);
    }
}

if (! function_exists('quoteTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteHttpRevenueProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (D-7), so a REVENUE line never trips the 422 coverage guard
     * (QuoteCoverageTest owns AC-050/051 directly).
     */
    function quoteHttpRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

// Spec 0077 name derivation, shared with the spec 0171 editable-title tests.
if (! function_exists('nameDerivationQuoteService')) {
    function nameDerivationQuoteService(): QuoteService
    {
        return app(QuoteService::class);
    }
}

if (! function_exists('nameDerivationNewQuoteWorkflowStatus')) {
    function nameDerivationNewQuoteWorkflowStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('nameDerivationActor')) {
    function nameDerivationActor(): User
    {
        return User::factory()->create();
    }
}

if (! function_exists('nameDerivationRevenueProduct')) {
    /**
     * A product whose category resolves an EFFECTIVE business function, so a
     * REVENUE line never trips OpportunityProductLineCoverage's 422 guard.
     */
    function nameDerivationRevenueProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['name' => $name, 'category_id' => $category->id]);
    }
}

if (! function_exists('nameDerivationCreateQuoteData')) {
    /**
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    function nameDerivationCreateQuoteData(int $opportunityId, ?array $offerLines = null, ?array $costLines = null): CreateQuoteData
    {
        return new CreateQuoteData(
            code: null,
            title: null,
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
            offerLines: $offerLines,
            costLines: $costLines,
        );
    }
}

if (! function_exists('nameDerivationRevenueLine')) {
    function nameDerivationRevenueLine(Product $product): QuoteLineData
    {
        return new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null);
    }
}

if (! function_exists('nameDerivationOpportunityCreatePayload')) {
    /**
     * The mandatory POST /api/opportunities payload beyond `name` (which is
     * never accepted from the client, AC-037): a fresh Registry/status/
     * supervisor plus a valid one-row `product_lines` + `products_of_interest`.
     *
     * @return array<string, mixed>
     */
    function nameDerivationOpportunityCreatePayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}
