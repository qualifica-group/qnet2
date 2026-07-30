<?php

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use Database\Seeders\DemoDocumentLayoutSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// migration/model — AC-001..004
// ---------------------------------------------------------------------------

it('schema: the 10 contract columns exist, no deleted_at/sort_order, is_active/is_default defaults (AC-001)', function () {
    foreach (['id', 'name', 'code', 'description', 'module', 'is_active', 'is_default', 'config', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('document_layouts', $column))->toBeTrue("missing column {$column}");
    }

    expect(Schema::hasColumn('document_layouts', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('document_layouts', 'sort_order'))->toBeFalse();

    $id = DB::table('document_layouts')->insertGetId([
        'name' => 'Default layout', 'code' => 'default_layout', 'module' => 'quotes',
        'config' => json_encode(['version' => 1]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $row = DB::table('document_layouts')->find($id);

    expect((bool) $row->is_active)->toBeTrue()
        ->and((bool) $row->is_default)->toBeFalse();
});

it('schema: code is unique globally at the DB level (AC-001, AC-004)', function () {
    DB::table('document_layouts')->insert(['name' => 'A', 'code' => 'unique_code', 'module' => 'quotes', 'config' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('document_layouts')->insert(['name' => 'B', 'code' => 'unique_code', 'module' => 'quotes', 'config' => '{}', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('schema: name is unique WITHIN a module, but the same name is allowed across two different modules (AC-004)', function () {
    DB::table('document_layouts')->insert(['name' => 'Standard', 'code' => 'std_a', 'module' => 'quotes', 'config' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('document_layouts')->insert(['name' => 'Standard', 'code' => 'std_b', 'module' => 'quotes', 'config' => '{}', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    // DocumentLayoutModule has a single case today (D-2/scope), so the
    // "same name in a different module" half of AC-004 is asserted at the DB
    // level directly (bypassing the enum cast) rather than invented against
    // a module the enum does not support.
    DB::table('document_layouts')->insert(['name' => 'Standard', 'code' => 'std_c', 'module' => 'invoices', 'config' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    expect(DB::table('document_layouts')->where('name', 'Standard')->count())->toBe(2);
});

it('migration: down() drops the table cleanly, up() recreates it (AC-002)', function () {
    $migration = require database_path('migrations/2026_07_31_090000_create_document_layouts_table.php');

    $migration->down();
    expect(Schema::hasTable('document_layouts'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('document_layouts'))->toBeTrue();
});

it('model: casts() returns module as DocumentLayoutModule, is_active/is_default as bool, config as a nested array (AC-003)', function () {
    $layout = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    $fresh = DocumentLayout::query()->findOrFail($layout->id);

    expect($fresh->module)->toBeInstanceOf(DocumentLayoutModule::class)
        ->and($fresh->module)->toBe(DocumentLayoutModule::Quotes)
        ->and($fresh->is_active)->toBeBool()
        ->and($fresh->is_default)->toBeBool()
        ->and($fresh->config)->toBeArray()
        ->and($fresh->config['page'])->toBeArray()
        ->and($fresh->config['page']['margins'])->toBeArray();
});

// ---------------------------------------------------------------------------
// DemoDocumentLayoutSeeder — AC-101
// ---------------------------------------------------------------------------

it('DemoDocumentLayoutSeeder creates the catalogued layouts with unique codes (AC-101)', function () {
    $this->seed(DemoDocumentLayoutSeeder::class);

    expect(DocumentLayout::count())->toBeGreaterThanOrEqual(2);

    $codes = DocumentLayout::query()->pluck('code');
    expect($codes->unique()->count())->toBe($codes->count())
        ->and($codes->all())->toContain('standard', 'accordo_sindacale');
});

it('DemoDocumentLayoutSeeder run twice leaves the same row count, idempotent via code (AC-101)', function () {
    $this->seed(DemoDocumentLayoutSeeder::class);
    $countAfterFirst = DocumentLayout::count();

    $this->seed(DemoDocumentLayoutSeeder::class);

    expect(DocumentLayout::count())->toBe($countAfterFirst);
});

it('DemoDocumentLayoutSeeder is never called from DatabaseSeeder', function () {
    $contents = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

    expect($contents)->not->toContain('DemoDocumentLayoutSeeder');
});
