<?php

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DatabaseMigrations, NOT RefreshDatabase: AC-004 replays `migrate:rollback`/
 * `migrate` for real, which exercises the SQLite table-rebuild path
 * `->change()` triggers on `quotes` (tightening `quote_workflow_status_id` to
 * NOT NULL) — a real `php artisan migrate` never wraps schema DDL in an
 * application transaction (SQLite grammar does not support schema
 * transactions), which the PRAGMA foreign_keys toggling that rebuild needs
 * requires. RefreshDatabase's per-test transaction would turn that PRAGMA
 * into a no-op — the same guard RewardStatusReshapeMigrationTest's own
 * docblock documents. DatabaseMigrations runs a real `migrate:fresh` before
 * EACH test and a real `migrate:rollback` after, so both tests below start
 * and end on a clean, fully-migrated baseline with no manual cleanup needed.
 */
uses(DatabaseMigrations::class);

it('rolls back all 7 new migrations cleanly and re-applies them (AC-004)', function () {
    // `--step` rolls back the N most-recent migrations regardless of which
    // spec they belong to, so the count must cover everything stacked ON TOP
    // of this AC's own 7 for them to be reached at all: spec 0084's
    // `2026_08_06_100000_add_quote_attribute_context_columns` (8th), spec
    // 0085's `2026_08_06_110000_add_quote_id_to_notes_table` (9th),
    // `2026_08_06_120000_drop_opportunity_attribute_context` (10th), and spec
    // 0086's `2026_08_07_100000_add_transfer_tracking_to_quotes_table` (11th),
    // `2026_08_07_100100_reset_rewards_for_quote_source` (12th) and
    // `2026_08_07_110000_reset_field_change_requests_for_quote_subject`
    // (13th), plus
    // `2026_08_07_120000_drop_validated_system_key_from_quote_workflow_statuses`
    // (14th) and
    // `2026_08_07_170000_add_single_quote_per_opportunity_to_product_categories_table`
    // (15th), `2026_08_31_100000_add_validated_contract_status` (16th), and
    // spec 0087's `2026_08_31_110000_create_quote_user_table` (17th),
    // `2026_08_31_110100_add_operator_id_to_quotes_table` (18th) and
    // `2026_08_31_120000_backfill_quote_managers_from_opportunity` (19th),
    // `2026_09_01_100000_create_units_of_measure_table` (20th),
    // `2026_09_01_100100_add_unit_of_measure_id_to_products_table` (21st),
    // `2026_09_01_100200_add_unit_of_measure_id_to_quote_lines_table` (22nd),
    // `2026_09_01_110000_add_recipient_to_commission_configurations_table`
    // (23rd) and
    // `2026_09_01_120000_drop_state_id_from_opportunities_and_products_tables`
    // (24th), spec 0090's
    // `2026_09_01_130000_add_user_id_to_referents_table` (25th) and
    // `2026_09_01_140000_add_generates_contract_to_product_categories_table`
    // (26th), spec 0093's `2026_09_02_100000_create_work_orders_table` (27th)
    // and `2026_09_02_100100_create_quote_line_work_order_table` (28th),
    // spec 0094's `2026_09_02_200010_create_project_product_lines_table`
    // (29th), `2026_09_02_200020_create_campaign_product_lines_table`
    // (30th), `2026_09_02_200030_move_project_classification_to_product_lines`
    // (31st), `2026_09_02_200040_move_campaign_classification_to_product_lines`
    // (32nd), `2026_09_02_200050_create_lead_product_table` (33rd) and
    // `2026_09_02_200060_add_product_ids_to_import_run_rows_table` (34th),
    // and spec 0095's
    // `2026_09_02_210000_change_quote_line_work_order_unique_constraint`
    // (35th). Adding a migration means bumping this number.
    Artisan::call('migrate:rollback', ['--step' => 35]);

    expect(Schema::hasTable('quote_workflows'))->toBeFalse()
        ->and(Schema::hasTable('opportunity_workflows'))->toBeTrue()
        ->and(Schema::hasTable('quote_statuses'))->toBeTrue()
        ->and(Schema::hasColumn('quotes', 'quote_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('quotes', 'quote_workflow_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'opportunity_workflow_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('product_categories', 'inherits_quote_attributes'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'attribute_values'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'attribute_values'))->toBeTrue()
        // The retired opportunity context's barrier comes back with its
        // migration's down(): structure is reversible, its rows are not.
        ->and(Schema::hasColumn('product_categories', 'inherits_opportunity_attributes'))->toBeTrue();

    Artisan::call('migrate', ['--step' => 35]);

    expect(Schema::hasTable('quote_workflows'))->toBeTrue()
        ->and(Schema::hasTable('opportunity_workflows'))->toBeFalse()
        ->and(Schema::hasTable('quote_statuses'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'quote_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'quote_workflow_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'opportunity_workflow_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('product_categories', 'inherits_quote_attributes'))->toBeTrue()
        ->and(Schema::hasColumn('quotes', 'attribute_values'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'attribute_values'))->toBeFalse()
        ->and(Schema::hasColumn('product_categories', 'inherits_opportunity_attributes'))->toBeFalse();
});

it('renames opportunity-workflows.* permissions and prunes quote-statuses.* ones in place', function () {
    $now = now();

    DB::table('permissions')->insert([
        ['name' => 'opportunity-workflows.viewAny', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ['name' => 'quote-statuses.viewAny', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
    ]);
    $workflowPermissionId = DB::table('permissions')->where('name', 'opportunity-workflows.viewAny')->value('id');

    $role = Role::create(['name' => 'quote-workflow-permission-rename-role']);
    $role->fieldPermissions()->create(['resource' => 'opportunity-workflows', 'field' => 'name']);
    $role->fieldPermissions()->create(['resource' => 'quote-statuses', 'field' => 'name']);
    $role->fieldPermissions()->create(['resource' => 'opportunities', 'field' => 'opportunity_workflow_status_id']);

    (require database_path('migrations/2026_08_05_120600_rename_and_prune_quote_workflow_permissions.php'))->up();

    expect(DB::table('permissions')->where('id', $workflowPermissionId)->value('name'))->toBe('quote-workflows.viewAny')
        ->and(DB::table('permissions')->where('name', 'like', 'quote-statuses.%')->exists())->toBeFalse()
        ->and(DB::table('role_field_permissions')->where('resource', 'quote-workflows')->exists())->toBeTrue()
        ->and(DB::table('role_field_permissions')->where('resource', 'opportunity-workflows')->exists())->toBeFalse()
        ->and(DB::table('role_field_permissions')->where('resource', 'quote-statuses')->exists())->toBeFalse()
        ->and(DB::table('role_field_permissions')->where('field', 'opportunity_workflow_status_id')->exists())->toBeFalse();
});
