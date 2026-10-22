<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\MigrationRun;
use App\Models\OperationalSite;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Http;

/*
 * Legacy RDA fixtures for the `purchase-requests` migration source tests
 * (spec 0294). The default record mirrors the frozen contract: one open RDA
 * with one pending line and no history, every anchor migrated by
 * legacyPurchaseRequestAnchors().
 */

if (! function_exists('legacyPurchaseRequestAnchors')) {
    /**
     * The migrated records the default fixture points at (legacy ids 10..16).
     *
     * @return array<string, mixed>
     */
    function legacyPurchaseRequestAnchors(): array
    {
        $company = Company::factory()->create();
        $company->forceFill(['old_id' => 12])->save();
        $companySite = CompanySite::factory()->create(['company_id' => $company->id]);
        $companySite->forceFill(['old_id' => 13])->save();
        $operationalSite = OperationalSite::factory()->create();
        $operationalSite->forceFill(['old_id' => 14])->save();
        $businessFunction = BusinessFunction::factory()->create();
        $businessFunction->forceFill(['old_id' => 15])->save();
        $workOrder = WorkOrder::factory()->create();
        $workOrder->forceFill(['old_id' => 16])->save();

        return [
            'requester' => User::factory()->create(['old_id' => 10]),
            'manager' => User::factory()->create(['old_id' => 11]),
            'company' => $company,
            'companySite' => $companySite,
            'operationalSite' => $operationalSite,
            'businessFunction' => $businessFunction,
            'workOrder' => $workOrder,
        ];
    }
}

if (! function_exists('legacyPurchaseRequestLine')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyPurchaseRequestLine(array $overrides = []): array
    {
        return array_merge([
            'id' => 900,
            'position' => 1,
            'product_id' => null,
            'description' => 'Legacy line',
            'supplier_id' => null,
            'unit_of_measure_code' => null,
            'quantity' => 2,
            'unit_price' => 10.5,
            'vat_rate_id' => null,
            'reason' => null,
            'status' => 'pending_approval',
            'approved_by_user_id' => null,
            'approved_at' => null,
            'status_logs' => [],
            'created_at' => '2025-03-01 08:00:00',
            'updated_at' => '2025-03-02 08:00:00',
        ], $overrides);
    }
}

if (! function_exists('legacyPurchaseRequestRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyPurchaseRequestRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 600,
            'subject' => 'Legacy RDA',
            'requested_at' => '2025-03-01',
            'priority_name' => 'Alta',
            'requester_user_id' => 10,
            'function_manager_user_id' => 11,
            'creator_user_id' => 10,
            'customer_id' => null,
            'work_order_id' => 16,
            'company_id' => 12,
            'company_site_id' => 13,
            'operational_site_id' => 14,
            'business_function_id' => 15,
            'notes' => null,
            'delivery_terms' => null,
            'procurement_plan' => null,
            'technical_requirements' => null,
            'special_conditions' => null,
            'is_closed' => false,
            'closed_by_user_id' => null,
            'closed_at' => null,
            'close_reason' => null,
            'lines' => [legacyPurchaseRequestLine()],
            'warnings' => [],
            'created_at' => '2025-03-01 08:00:00',
            'updated_at' => '2025-03-05 08:00:00',
        ], $overrides);
    }
}

if (! function_exists('runLegacyPurchaseRequestsImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyPurchaseRequestsImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/purchase-requests*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'purchase-requests']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}
