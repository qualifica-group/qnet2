<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unit of measure entity (spec 0088, D-1): a lean lookup (code/name/symbol/
 * description) used to classify a Product's quantity, mirroring VatRate for
 * leanness. `code` is the ONLY addition beyond the requested fields (D-1):
 * snake_case, unique, immutable after create (`prohibited` in update) — it
 * lets the Products migration below, and ProductService's default
 * resolution, target the default row stably even if `name`/`symbol` are
 * later renamed.
 *
 * The default row (`code='unit'`, `name='Unita'`, `symbol='pz'`) is inserted
 * HERE, not by a seeder (D-2): migrations run before seeders, and this is
 * the only way to guarantee the very next migration
 * (products.unit_of_measure_id backfill) has a row to point every existing
 * product at. Plain query-builder insert, not the Model, so this migration
 * never depends on application code.
 */
return new class extends Migration
{
    private const string DEFAULT_CODE = 'unit';

    private const string DEFAULT_NAME = 'Unita';

    private const string DEFAULT_SYMBOL = 'pz';

    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191)->unique();
            $table->string('symbol', 16)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        DB::table('units_of_measure')->insert([
            'code' => self::DEFAULT_CODE,
            'name' => self::DEFAULT_NAME,
            'symbol' => self::DEFAULT_SYMBOL,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
