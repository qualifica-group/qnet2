<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\VatRate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the vat_rates table with the expected columns', function () {
    expect(Schema::hasTable('vat_rates'))->toBeTrue();
    expect(Schema::hasColumns('vat_rates', ['id', 'name', 'rate', 'created_at', 'updated_at']))->toBeTrue();
});

it('name and rate are required at the database level', function () {
    expect(fn () => DB::table('vat_rates')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('vat_rates')->insert(['name' => 'X', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_105000_create_vat_rates_table.php');

    $migration->down();

    expect(Schema::hasTable('vat_rates'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('vat_rates'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// model
// ---------------------------------------------------------------------------

it('logs model activity on the vat-rates log channel', function () {
    expect(class_uses(VatRate::class))->toHaveKey(LogsModelActivity::class);
});

it('casts rate to a 2-decimal string', function () {
    $vatRate = VatRate::factory()->create(['rate' => 22]);

    expect($vatRate->fresh()->rate)->toBe('22.00');
});

it('is fillable for name and rate', function () {
    $vatRate = VatRate::create(['name' => 'IVA 22%', 'rate' => 22]);

    expect($vatRate->name)->toBe('IVA 22%')
        ->and($vatRate->rate)->toBe('22.00');
});
