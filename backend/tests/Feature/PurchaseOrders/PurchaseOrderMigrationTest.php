<?php

use App\Models\CostType;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * DatabaseMigrations, NOT RefreshDatabase: the test replays a real
 * `migrate:rollback`/`migrate` (same reason as QuoteWorkflowMigrationTest).
 */
uses(DatabaseMigrations::class);

it('rolls back the six purchase order tables (and the payable ones built on them) and re-applies them with the seeded cost types', function () {
    $tables = [
        'cost_types', 'purchase_order_number_sequences', 'purchase_orders', 'purchase_order_lines',
        'purchase_order_sector', 'purchase_order_merge_logs',
    ];

    // 17 steps: from `2026_10_13_100000_create_cost_types_table` to the newest migration, main's interleaved
    // ones included (users service account, token api_client_id, sector flag and code, work order title flag,
    // quote date). Adding a migration means bumping this number.
    expect(Artisan::call('migrate:rollback', ['--step' => 17]))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }

    expect(Artisan::call('migrate'))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    expect(CostType::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Collaboratori', 'Fitti', 'Utenze', 'Altro']);
});
