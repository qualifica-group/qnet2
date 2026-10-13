<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0247 D-6 / AC-010. A minimal copy of product_typologies is built in the
 * in-memory database and the real migration file is run against it.
 */
function proformaKindMigration(): Migration
{
    return require database_path('migrations/2026_10_17_140000_add_proforma_kind_to_product_typologies_table.php');
}

function legacyProductTypologySchema(): void
{
    Schema::create('product_typologies', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('supplier_commission_direction', 16)->nullable();
    });
}

it('AC-010: backfills proforma_kind for the consultancy and institution codes only', function () {
    legacyProductTypologySchema();
    DB::table('product_typologies')->insert([['code' => 'consultancy'], ['code' => 'institution'], ['code' => 'training']]);

    proformaKindMigration()->up();

    expect(DB::table('product_typologies')->pluck('proforma_kind', 'code')->all())
        ->toBe(['consultancy' => 'consultancy', 'institution' => 'institution', 'training' => null]);
});

it('AC-010: rolling back drops the column', function () {
    legacyProductTypologySchema();
    $migration = proformaKindMigration();
    $migration->up();

    $migration->down();

    expect(Schema::hasColumn('product_typologies', 'proforma_kind'))->toBeFalse();
});
