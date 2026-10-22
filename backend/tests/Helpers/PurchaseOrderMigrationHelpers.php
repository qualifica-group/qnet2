<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\MigrationRun;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * Legacy ODA fixtures for the `purchase-orders` migration source tests
 * (spec 0296). The default record mirrors the frozen contract: one draft
 * order with one line and no link, every required anchor migrated by
 * legacyPurchaseOrderAnchors().
 */

if (! function_exists('legacyPurchaseOrderAnchors')) {
    /**
     * The migrated records the default fixture points at (legacy ids 70..73).
     *
     * @return array<string, mixed>
     */
    function legacyPurchaseOrderAnchors(): array
    {
        $company = Company::factory()->create();
        $company->forceFill(['old_id' => 71])->save();
        $companySite = CompanySite::factory()->create(['company_id' => $company->id]);
        $companySite->forceFill(['old_id' => 72])->save();

        return [
            'creator' => User::factory()->create(['old_id' => 70]),
            'company' => $company,
            'companySite' => $companySite,
            'supplier' => Registry::factory()->create(['old_id' => 73]),
        ];
    }
}

if (! function_exists('legacyPurchaseOrderLine')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyPurchaseOrderLine(array $overrides = []): array
    {
        return array_merge([
            'id' => 1500,
            'position' => 1,
            'description' => 'Legacy order line',
            'cost_type_name' => 'Altro',
            'quantity' => 2,
            'unit_price' => 10.5,
            'vat_rate_id' => null,
            'work_order_id' => null,
            'purchase_request_line_id' => null,
        ], $overrides);
    }
}

if (! function_exists('legacyPurchaseOrderRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyPurchaseOrderRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 800,
            'number' => 12,
            'year_max_number' => 40,
            'protocol' => '05/03/2025-120',
            'order_date' => '2025-03-05',
            'description' => 'Legacy ODA',
            'general_notes' => null,
            'supplier_id' => 73,
            'customer_id' => null,
            'company_id' => 71,
            'company_site_id' => 72,
            'operational_site_id' => null,
            'payment_method_id' => null,
            'is_internal_user' => false,
            'sector_notes' => null,
            'status' => 'draft',
            'creator_user_id' => 70,
            'lines' => [legacyPurchaseOrderLine()],
            'warnings' => [],
            'created_at' => '2025-03-05 08:00:00',
            'updated_at' => '2025-03-09 08:00:00',
        ], $overrides);
    }
}

if (! function_exists('runLegacyPurchaseOrdersImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyPurchaseOrdersImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/purchase-orders*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'purchase-orders']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}
