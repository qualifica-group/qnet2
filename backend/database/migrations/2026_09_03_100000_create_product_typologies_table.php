<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ProductTypology entity (spec 0099, D-1/D-2): a lean lookup (code/name/
 * description) classifying a Product, mirroring UnitOfMeasure — the
 * structural twin this whole module follows.
 *
 * `code` is the only addition beyond the requested `name` (D-2), and it is
 * what makes requirement 7 enforceable: ProductService resolves the default
 * typology by CODE, so no application logic ever compares the name string
 * "Ente". snake_case, unique, immutable after create (`prohibited` in
 * update).
 *
 * The default row (`code='institution'`, `name='Ente'`) is inserted HERE, not
 * by a seeder (D-4): migrations run before seeders, and this is the only way
 * to guarantee the very next migration (products.product_typology_id
 * backfill) has a row to point every existing product at. Plain
 * query-builder insert, not the Model, so this migration never depends on
 * application code. ProductTypologySeeder then firstOrCreate()s this same
 * row alongside "Consulenza", finding it without duplicating it.
 */
return new class extends Migration
{
    private const string DEFAULT_CODE = 'institution';

    private const string DEFAULT_NAME = 'Ente';

    public function up(): void
    {
        Schema::create('product_typologies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        DB::table('product_typologies')->insert([
            'code' => self::DEFAULT_CODE,
            'name' => self::DEFAULT_NAME,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_typologies');
    }
};
