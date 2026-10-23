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

    // Covers every migration stacked on top, up to spec 0254's three receipt migrations, spec 0189 G-28's
    // two Italian geo reference fixes, spec 0267's five settlement document migrations and spec 0267's three
    // multi-quote migrations, plus spec 0271's four HR org structure migrations, spec 0272's 7 employee record migrations
    // and the hourly cost migration, plus spec 0277's two hourly cost removal migrations, plus spec 0278's four absence migrations, plus spec 0280's business function active flag, plus spec 0284's two staff training migrations (REQUIREMENT CHANGED), plus spec 0286's three skill migrations (REQUIREMENT CHANGED), plus spec 0289's tasks old_id migration (REQUIREMENT CHANGED), plus spec 0290's two contract type migrations (REQUIREMENT CHANGED), plus spec 0291's working time migration (REQUIREMENT CHANGED), plus spec 0293's CCNL removal migration (REQUIREMENT CHANGED).
    // Spec 0306 adds two migrations on top (REQUIREMENT CHANGED: 86 -> 88).
    // Spec 0307 adds two more (stock balances and movements; REQUIREMENT CHANGED: 88 -> 90).
    expect(Artisan::call('migrate:rollback', ['--step' => 90]))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }

    expect(Artisan::call('migrate'))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    expect(CostType::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Collaboratori', 'Fitti', 'Utenze', 'Altro']);
});
