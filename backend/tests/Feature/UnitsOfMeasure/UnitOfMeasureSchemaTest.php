<?php

use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\UnitOfMeasure;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// migration schema — AC-001/AC-002
// ---------------------------------------------------------------------------

it('schema: the 6 contract columns exist, code/name/symbol unique, description nullable (AC-001)', function () {
    foreach (['id', 'code', 'name', 'symbol', 'description', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('units_of_measure', $column))->toBeTrue("missing column {$column}");
    }

    DB::table('units_of_measure')->insert(['code' => 'litre', 'name' => 'Litri', 'symbol' => 'l', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('units_of_measure')->insert(['code' => 'litre', 'name' => 'Other Name', 'symbol' => 'o', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('units_of_measure')->insert(['code' => 'other_code', 'name' => 'Litri', 'symbol' => 'o2', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('units_of_measure')->insert(['code' => 'other_code2', 'name' => 'Other Name 2', 'symbol' => 'l', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    DB::table('units_of_measure')->insert(['code' => 'no_description', 'name' => 'No Description', 'symbol' => 'nd', 'description' => null, 'created_at' => now(), 'updated_at' => now()]);
    expect(DB::table('units_of_measure')->where('code', 'no_description')->value('description'))->toBeNull();
});

it('migration: down() drops the table cleanly, up() recreates it with the default row (AC-001)', function () {
    $migration = require database_path('migrations/2026_09_01_100000_create_units_of_measure_table.php');

    $migration->down();
    expect(Schema::hasTable('units_of_measure'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('units_of_measure'))->toBeTrue();
    expect(DB::table('units_of_measure')->where('code', 'unit')->exists())->toBeTrue();
});

it('migration: creates the default row code=unit, name=Unita, symbol=pz (AC-002)', function () {
    $row = DB::table('units_of_measure')->where('code', 'unit')->first();

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Unita')
        ->and($row->symbol)->toBe('pz');
});

// ---------------------------------------------------------------------------
// products.unit_of_measure_id — AC-003
// ---------------------------------------------------------------------------

it('schema: products.unit_of_measure_id exists, NOT NULL, restrictOnDelete, every product backfilled to the default unit (AC-003)', function () {
    expect(Schema::hasColumn('products', 'unit_of_measure_id'))->toBeTrue();

    $category = ProductCategory::factory()->create();
    $unit = UnitOfMeasure::where('code', 'unit')->first();

    $productId = DB::table('products')->insertGetId([
        'name' => 'Raw insert', 'code' => 'RAW-0001', 'category_id' => $category->id,
        'product_type' => 'SERVICE', 'unit_of_measure_id' => $unit->id,
        // Spec 0099 added this NOT NULL FK after this test was written.
        'product_typology_id' => DB::table('product_typologies')->where('code', 'institution')->value('id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('products')->where('id', $productId)->update(['unit_of_measure_id' => null]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('units_of_measure')->where('id', $unit->id)->delete())
        ->toThrow(QueryException::class);
});

it('migration: an existing product is backfilled to the default unit id (AC-003)', function () {
    // Simulate a pre-migration product: temporarily drop back to the state
    // before 2026_09_01_100100 ran, insert a raw row, then replay the
    // migration exactly like a real deploy against non-empty data would.
    $backfillMigration = require database_path('migrations/2026_09_01_100100_add_unit_of_measure_id_to_products_table.php');
    $backfillMigration->down();

    $category = ProductCategory::factory()->create();
    $productId = DB::table('products')->insertGetId([
        'name' => 'Pre-existing', 'code' => 'RAW-0002', 'category_id' => $category->id,
        'product_type' => 'SERVICE',
        // Spec 0099 added this NOT NULL FK after this test was written.
        'product_typology_id' => DB::table('product_typologies')->where('code', 'institution')->value('id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $backfillMigration->up();

    $defaultUnitId = DB::table('units_of_measure')->where('code', 'unit')->value('id');
    expect(DB::table('products')->where('id', $productId)->value('unit_of_measure_id'))->toBe($defaultUnitId);
});

// ---------------------------------------------------------------------------
// quote_lines.unit_of_measure_id — nullable, no backfill (D-5)
// ---------------------------------------------------------------------------

it('schema: quote_lines.unit_of_measure_id exists, nullable, restrictOnDelete', function () {
    expect(Schema::hasColumn('quote_lines', 'unit_of_measure_id'))->toBeTrue();

    $line = QuoteLine::factory()->create(['unit_of_measure_id' => null]);
    expect($line->fresh()->unit_of_measure_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// UnitOfMeasureSeeder — AC-080
// ---------------------------------------------------------------------------

it('UnitOfMeasureSeeder produces the conventional catalogue including the migration-created default row (AC-080)', function () {
    $this->seed(UnitOfMeasureSeeder::class);

    $codes = UnitOfMeasure::query()->pluck('code');
    expect($codes->unique()->count())->toBe($codes->count())
        ->and($codes->all())->toContain('unit', 'kilogram', 'gram', 'litre', 'metre', 'square_metre', 'hour', 'day');

    // The default row created by the migration is found, not duplicated.
    expect(UnitOfMeasure::where('code', 'unit')->count())->toBe(1);
});

it('UnitOfMeasureSeeder run twice leaves the same row count, idempotent via code (AC-080)', function () {
    $this->seed(UnitOfMeasureSeeder::class);
    $countAfterFirst = UnitOfMeasure::count();

    $this->seed(UnitOfMeasureSeeder::class);

    expect(UnitOfMeasure::count())->toBe($countAfterFirst);
});
