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
    // Spec 0084 added one more migration on top of these 7 (the newest one,
    // `2026_08_06_100000_add_quote_attribute_context_columns`) — `--step`
    // rolls back the N most-recent migrations regardless of which spec they
    // belong to, so it must cover that 8th one too for this AC's own 7 to be
    // reached at all.
    Artisan::call('migrate:rollback', ['--step' => 8]);

    expect(Schema::hasTable('quote_workflows'))->toBeFalse()
        ->and(Schema::hasTable('opportunity_workflows'))->toBeTrue()
        ->and(Schema::hasTable('quote_statuses'))->toBeTrue()
        ->and(Schema::hasColumn('quotes', 'quote_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('quotes', 'quote_workflow_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'opportunity_workflow_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('product_categories', 'inherits_quote_attributes'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'attribute_values'))->toBeFalse()
        ->and(Schema::hasColumn('opportunities', 'attribute_values'))->toBeTrue();

    Artisan::call('migrate', ['--step' => 8]);

    expect(Schema::hasTable('quote_workflows'))->toBeTrue()
        ->and(Schema::hasTable('opportunity_workflows'))->toBeFalse()
        ->and(Schema::hasTable('quote_statuses'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'quote_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('quotes', 'quote_workflow_status_id'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'opportunity_workflow_status_id'))->toBeFalse()
        ->and(Schema::hasColumn('product_categories', 'inherits_quote_attributes'))->toBeTrue()
        ->and(Schema::hasColumn('quotes', 'attribute_values'))->toBeTrue()
        ->and(Schema::hasColumn('opportunities', 'attribute_values'))->toBeFalse();
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
