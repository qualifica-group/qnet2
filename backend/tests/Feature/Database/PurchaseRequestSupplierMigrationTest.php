<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0209 AC-001. No RefreshDatabase on purpose: the migration drops a
 * constrained column of the header table and SQLite rebuilds the table for
 * that, so the foreign-key pragma (a no-op inside a transaction) must be
 * effective. A minimal copy of the tables the migration touches is built in the
 * in-memory database and the real migration file is run against it.
 */
function supplierMigration(): Migration
{
    return require database_path('migrations/2026_10_12_110000_move_supplier_to_purchase_request_lines.php');
}

function legacyPurchaseRequestSchema(): void
{
    Schema::create('registries', fn (Blueprint $table) => $table->id());
    Schema::create('purchase_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
    });
    Schema::create('purchase_request_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
        $table->string('description')->default('');
        $table->unsignedInteger('position');
    });
    Schema::create('role_field_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('resource');
        $table->string('field');
    });
}

it('AC-001: copies the header supplier onto every line, drops the header column and the field permission rows', function () {
    legacyPurchaseRequestSchema();
    DB::table('registries')->insert([['id' => 7]]);
    DB::table('purchase_requests')->insert([['id' => 1, 'supplier_id' => 7], ['id' => 2, 'supplier_id' => null]]);
    DB::table('purchase_request_lines')->insert([
        ['purchase_request_id' => 1, 'position' => 1], ['purchase_request_id' => 1, 'position' => 2],
        ['purchase_request_id' => 1, 'position' => 3], ['purchase_request_id' => 2, 'position' => 1],
    ]);
    DB::table('role_field_permissions')->insert([
        ['resource' => 'purchase-requests', 'field' => 'supplier_id'],
        ['resource' => 'purchase-requests', 'field' => 'subject'],
        ['resource' => 'quotes', 'field' => 'supplier_id'],
    ]);

    supplierMigration()->up();

    expect(Schema::hasColumn('purchase_requests', 'supplier_id'))->toBeFalse()
        ->and(DB::table('purchase_request_lines')->where('purchase_request_id', 1)->pluck('supplier_id')->all())->toBe([7, 7, 7])
        ->and(DB::table('purchase_request_lines')->where('purchase_request_id', 2)->pluck('supplier_id')->all())->toBe([null])
        ->and(DB::table('role_field_permissions')->orderBy('id')->pluck('field', 'resource')->all())->toBe(['purchase-requests' => 'subject', 'quotes' => 'supplier_id']);
});

it('AC-001: rolling back restores the header supplier from the first line that has one', function () {
    legacyPurchaseRequestSchema();
    $migration = supplierMigration();
    $migration->up();
    DB::table('registries')->insert([['id' => 7], ['id' => 8]]);
    DB::table('purchase_requests')->insert([['id' => 1], ['id' => 2]]);
    DB::table('purchase_request_lines')->insert([
        ['purchase_request_id' => 1, 'position' => 1, 'supplier_id' => null],
        ['purchase_request_id' => 1, 'position' => 3, 'supplier_id' => 8],
        ['purchase_request_id' => 1, 'position' => 2, 'supplier_id' => 7],
        ['purchase_request_id' => 2, 'position' => 1, 'supplier_id' => null],
    ]);

    $migration->down();

    expect(Schema::hasColumn('purchase_request_lines', 'supplier_id'))->toBeFalse()
        ->and(DB::table('purchase_requests')->orderBy('id')->pluck('supplier_id')->all())->toBe([7, null])
        ->and(DB::table('purchase_request_lines')->count())->toBe(4);
});
