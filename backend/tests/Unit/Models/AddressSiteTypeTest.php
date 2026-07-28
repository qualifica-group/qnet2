<?php

use App\Enums\SiteTypeEnum;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-002 (spec 0020) — schema
// ---------------------------------------------------------------------------

it('adds addresses.site_type as a NOT NULL column defaulting to billing', function () {
    expect(Schema::hasColumn('addresses', 'site_type'))->toBeTrue();

    $id = DB::table('addresses')->insertGetId([
        'line1' => 'Via Roma 1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('addresses')->find($id)->site_type)->toBe('billing');
});

// ---------------------------------------------------------------------------
// AC-002/AC-007-style — model cast
// ---------------------------------------------------------------------------

it('casts site_type to SiteTypeEnum on the model', function () {
    $address = Address::factory()->create(['site_type' => 'legal_seat']);

    expect($address->fresh()->site_type)->toBe(SiteTypeEnum::LegalSeat);
});
