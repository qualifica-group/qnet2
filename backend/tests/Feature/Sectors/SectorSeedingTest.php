<?php

use App\Models\Sector;
use Database\Seeders\QualificaCatalog\SectorCatalogue;
use Database\Seeders\QualificaLegacyImportSeeder;
use Database\Seeders\QualificaSectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-004 — the EA catalogue, active, codes verbatim, idempotent
// ---------------------------------------------------------------------------

it('AC-004: seeds the 44 EA sectors active, as roots, with their codes verbatim', function () {
    $this->seed(QualificaSectorSeeder::class);

    expect(Sector::query()->count())->toBe(44)
        ->and(Sector::query()->where('is_active', false)->exists())->toBeFalse()
        ->and(Sector::query()->whereNotNull('parent_id')->exists())->toBeFalse()
        ->and(Sector::query()->orderBy('id')->pluck('code')->all())->toBe(array_map('strval', array_keys(SectorCatalogue::SECTORS)))
        ->and(Sector::firstWhere('code', '01')->name)->toBe('Agricoltura, silvicoltura e pesca')
        ->and(Sector::firstWhere('code', '07a')->name)->toBe('Prodotti in carta')
        ->and(Sector::firstWhere('code', '10')->name)->toBe('Fabbricazione di coke e di prodotti petroliferi raffinati')
        ->and(Sector::firstWhere('code', 'NA')->name)->toBe('AMMESSO CARICAMENTO SENZA SETTORE');
});

it('AC-004: a re-run neither duplicates nor overwrites a rename or a deactivation', function () {
    $this->seed(QualificaSectorSeeder::class);
    Sector::firstWhere('code', '01')->update(['name' => 'Agricoltura', 'is_active' => false]);

    $this->seed(QualificaSectorSeeder::class);

    $sector = Sector::firstWhere('code', '01');
    expect(Sector::query()->count())->toBe(44)
        ->and($sector->name)->toBe('Agricoltura')
        ->and($sector->is_active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-005 — the legacy import leaves every legacy sector inactive
// ---------------------------------------------------------------------------

it('AC-005: the legacy import deactivates every legacy sector, the catalogue stays active', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    Http::fake([
        fakeMigrationsBaseUrl().'/sectors*' => Http::response([
            'items' => [
                ['id' => 501, 'name' => 'Legacy Root', 'parent_id' => null],
                ['id' => 502, 'name' => 'Legacy Child', 'parent_id' => 501],
            ],
            'pagination' => ['total' => 2],
        ]),
        fakeMigrationsBaseUrl().'/*' => Http::response(['items' => [], 'pagination' => ['total' => 0]]),
    ]);

    $catalogue = Sector::factory()->create(['code' => '01']);
    // A legacy sector from an earlier run, reactivated by hand (D-2: still off).
    $reactivated = Sector::factory()->create();
    $reactivated->old_id = 499;
    $reactivated->save();

    $this->seed(QualificaLegacyImportSeeder::class);

    expect(Sector::query()->whereNotNull('old_id')->count())->toBe(3)
        ->and(Sector::query()->whereNotNull('old_id')->where('is_active', true)->exists())->toBeFalse()
        ->and($reactivated->fresh()->is_active)->toBeFalse()
        ->and($catalogue->fresh()->is_active)->toBeTrue();
});
