<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0247 AC-001. No RefreshDatabase on purpose: the migration drops an
 * indexed column and makes a foreign key required, and SQLite rebuilds the
 * table for that, so the foreign-key pragma (a no-op inside a transaction) must
 * be effective. A minimal copy of the tables the migration touches is built in
 * the in-memory database and the real migration file is run against it.
 */
function purchaseRequestPriorityMigration(): Migration
{
    return require database_path('migrations/2026_10_17_120000_create_purchase_request_priorities_table.php');
}

function legacyPurchaseRequestPrioritySchema(): void
{
    Schema::create('purchase_requests', function (Blueprint $table) {
        $table->id();
        $table->string('subject');
        $table->date('requested_at')->nullable();
        $table->string('priority', 20);
        $table->index('priority');
    });
    Schema::create('role_field_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('resource');
        $table->string('field');
    });
}

it('AC-001: creates the five priorities with Media as default and backfills every request from the old value', function () {
    legacyPurchaseRequestPrioritySchema();
    DB::table('purchase_requests')->insert([
        ['subject' => 'a', 'priority' => 'low'], ['subject' => 'b', 'priority' => 'medium'],
        ['subject' => 'c', 'priority' => 'high'], ['subject' => 'd', 'priority' => 'urgent'],
        ['subject' => 'e', 'priority' => 'critical'], ['subject' => 'f', 'priority' => 'high'],
    ]);
    DB::table('role_field_permissions')->insert([
        ['resource' => 'purchase-requests', 'field' => 'priority'],
        ['resource' => 'quotes', 'field' => 'priority'],
    ]);

    purchaseRequestPriorityMigration()->up();

    $priorities = DB::table('purchase_request_priorities')->orderBy('sort_order')->get();
    expect($priorities->pluck('name')->all())->toBe(['Bassa', 'Media', 'Alta', 'Urgente', 'Critica'])
        ->and($priorities->pluck('color')->all())->toBe(['slate', 'blue', 'amber', 'orange', 'red'])
        ->and($priorities->where('is_default', 1)->pluck('name')->all())->toBe(['Media'])
        ->and($priorities->every(fn (object $row): bool => (bool) $row->is_active))->toBeTrue()
        ->and(Schema::hasColumn('purchase_requests', 'priority'))->toBeFalse();

    $byName = $priorities->pluck('id', 'name');
    $assigned = DB::table('purchase_requests')->orderBy('subject')->pluck('purchase_request_priority_id', 'subject')->all();
    expect($assigned)->toBe([
        'a' => $byName['Bassa'], 'b' => $byName['Media'], 'c' => $byName['Alta'],
        'd' => $byName['Urgente'], 'e' => $byName['Critica'], 'f' => $byName['Alta'],
    ])
        ->and(DB::table('role_field_permissions')->orderBy('id')->pluck('field', 'resource')->all())
        ->toBe(['purchase-requests' => 'purchase_request_priority_id', 'quotes' => 'priority']);
});

it('AC-001: the priority column becomes required after the backfill', function () {
    legacyPurchaseRequestPrioritySchema();
    purchaseRequestPriorityMigration()->up();

    expect(fn () => DB::table('purchase_requests')->insert(['subject' => 'orphan']))->toThrow(QueryException::class);
});

it('AC-001: rolling back restores the old column with the values and drops the lookup', function () {
    legacyPurchaseRequestPrioritySchema();
    DB::table('purchase_requests')->insert([['subject' => 'a', 'priority' => 'urgent'], ['subject' => 'b', 'priority' => 'low']]);
    $migration = purchaseRequestPriorityMigration();
    $migration->up();
    $custom = DB::table('purchase_request_priorities')->insertGetId(['name' => 'Custom', 'color' => 'teal', 'sort_order' => 9, 'is_active' => true, 'is_default' => false]);
    DB::table('purchase_requests')->insert(['subject' => 'c', 'purchase_request_priority_id' => $custom]);

    $migration->down();

    expect(Schema::hasTable('purchase_request_priorities'))->toBeFalse()
        ->and(Schema::hasColumn('purchase_requests', 'purchase_request_priority_id'))->toBeFalse()
        ->and(DB::table('purchase_requests')->orderBy('subject')->pluck('priority', 'subject')->all())
        ->toBe(['a' => 'urgent', 'b' => 'low', 'c' => 'medium']);
});
