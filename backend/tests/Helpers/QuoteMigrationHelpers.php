<?php

use App\Models\BusinessFunction;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * Legacy quote fixtures shared by the `quotes` migration source tests
 * (QuotesSourceImportTest, QuotesSourceAttributeValuesImportTest).
 */

if (! function_exists('legacyQuoteServiceProduct')) {
    /**
     * A sellable product migrated from the legacy `services` table, under a
     * category with a business function so the opportunity coverage holds.
     */
    function legacyQuoteServiceProduct(int $legacyId): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->saleOnly()->create([
            'category_id' => $category->id,
            'product_typology_id' => ProductTypology::factory()->supplierCommission(),
            'old_source' => 'services',
            'old_id' => $legacyId,
        ]);
    }
}

if (! function_exists('legacyQuoteRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyQuoteRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 42,
            'opportunity_id' => 7,
            'title' => 'Legacy offer',
            'description' => 'Legacy description',
            'quote_date' => '2024-03-01',
            'outcome' => 'open',
            'contract' => null,
            'customer_differs_from_opportunity' => false,
            'commercial_referent_id' => null,
            'reporter_referent_id' => null,
            'supervisor_user_id' => null,
            'manager_user_ids' => [],
            'company_site_id' => null,
            'operational_site_id' => null,
            'payment_method_id' => null,
            // REQUIREMENT CHANGED: spec 0332 — the legacy sends the Fonte on the opportunity's first quotation only.
            'source_id' => null,
            'lines' => [
                ['id' => 900, 'product_id' => 10, 'description' => null, 'quantity' => 2, 'unit_price' => 50, 'vat_rate_id' => null, 'commissions' => []],
            ],
            'created_at' => '2024-03-01 10:00:00',
            'updated_at' => '2024-03-05 11:00:00',
        ], $overrides);
    }
}

if (! function_exists('runLegacyQuotesImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyQuotesImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/quotes*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'quotes']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

if (! function_exists('legacyQuoteWarnings')) {
    /**
     * @return array<int, string>
     */
    function legacyQuoteWarnings(MigrationRun $run): array
    {
        return collect($run->report)->where('level', 'warning')->pluck('message')->values()->all();
    }
}
