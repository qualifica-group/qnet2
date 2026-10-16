<?php

use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * Legacy work order fixtures shared by the `work-orders` migration source tests
 * (WorkOrdersSourceImportTest, WorkOrdersSourceAttributeValuesImportTest,
 * WorkOrdersSourceCostsAndPaymentsImportTest). The legacy never sends a
 * commessa without lines (2026-10-14): the default record carries line 900.
 */

if (! function_exists('legacyWorkOrderRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyWorkOrderRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 17,
            'quote_id' => 42,
            'title' => 'Legacy work order',
            'type' => 'project',
            'start_date' => '2024-05-01',
            'callback_date' => '2024-06-01',
            'description' => 'Legacy description',
            'internal_notes' => 'Legacy notes',
            'force_close_reason' => null,
            'status_label' => 'Aperta',
            'stato_pagamento' => null,
            'accordo_pagamento' => null,
            'insoluti' => false,
            'quote_line_ids' => [900],
            'supervisor_user_ids' => [],
            'participant_user_ids' => [],
            'costs' => [],
            'created_at' => '2024-04-20 09:00:00',
            'updated_at' => '2024-04-21 09:00:00',
        ], $overrides);
    }
}

if (! function_exists('runLegacyWorkOrdersImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyWorkOrdersImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/work-orders*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'work-orders']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

if (! function_exists('legacyWorkOrderWarnings')) {
    /**
     * @return array<int, string>
     */
    function legacyWorkOrderWarnings(MigrationRun $run): array
    {
        return collect($run->report)->where('level', 'warning')->pluck('message')->values()->all();
    }
}
