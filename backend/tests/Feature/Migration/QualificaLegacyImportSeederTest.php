<?php

use App\Enums\MigrationStatus;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\QualificaLegacyImportSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor) are defined once, guarded by function_exists,
// across the Migration feature suite (see CompaniesSourceImportTest).
if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

/**
 * Every legacy catalogue empty except `tags` (a legacy-only row) and `sources`
 * (one name the static template already ships + one it does not). Specific
 * patterns first: Http::fake matches in declaration order.
 */
function fakeLegacyCatalogues(): void
{
    Http::fake([
        fakeMigrationsBaseUrl().'/tags*' => Http::response([
            'items' => [['id' => 71, 'name' => 'Legacy Tag']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [
                ['id' => 81, 'name' => 'Passaparola'],
                ['id' => 82, 'name' => 'Fiera'],
            ],
            'pagination' => ['total' => 2],
        ]),
        fakeMigrationsBaseUrl().'/*' => Http::response(['items' => [], 'pagination' => ['total' => 0]]),
    ]);
}

it('runs the fixed source list as one inline mass run and completes it', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    test()->seed(QualificaTemplateSeeder::class);

    $massRun = MassMigrationRun::query()->sole();

    expect($massRun->sources)->toBe(QualificaLegacyImportSeeder::SOURCES)
        ->and($massRun->status)->toBe(MigrationStatus::Completed)
        ->and($massRun->runs()->count())->toBe(count(QualificaLegacyImportSeeder::SOURCES))
        // Child runs created in plan order: the phase order is the contract.
        ->and(MigrationRun::query()->orderBy('id')->pluck('source')->all())->toBe(QualificaLegacyImportSeeder::SOURCES)
        ->and(Tag::query()->where('old_id', 71)->value('name'))->toBe('Legacy Tag');
});

it('adopts a template source instead of duplicating it, and imports the legacy-only one', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    test()->seed(QualificaTemplateSeeder::class);

    expect(Source::query()->where('name', 'Passaparola')->count())->toBe(1)
        ->and(Source::query()->where('name', 'Passaparola')->value('old_id'))->toBe(81)
        ->and(Source::query()->where('name', 'Fiera')->value('old_id'))->toBe(82);
});

it('re-running the template never duplicates an imported catalogue', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    test()->seed(QualificaTemplateSeeder::class);
    $afterFirst = Source::query()->count();

    test()->seed(QualificaTemplateSeeder::class);

    expect(Source::query()->count())->toBe($afterFirst)
        ->and(Tag::query()->where('name', 'Legacy Tag')->count())->toBe(1)
        ->and(MassMigrationRun::query()->count())->toBe(2)
        ->and(MassMigrationRun::query()->latest('id')->first()->status)->toBe(MigrationStatus::Completed);
});

it('skips the import when no external system is configured', function () {
    config(['migrations.base_url' => null]);
    migrationsSuperAdminActor();
    Http::preventStrayRequests();

    test()->seed(QualificaTemplateSeeder::class);

    // The static template still lands; only the legacy step is skipped.
    expect(MassMigrationRun::query()->count())->toBe(0)
        ->and(Source::query()->where('name', 'Passaparola')->count())->toBe(1);
});

it('skips the import when no super-admin exists to run it as', function () {
    seedMigrationsConfig();
    Http::preventStrayRequests();

    test()->seed(QualificaTemplateSeeder::class);

    expect(MassMigrationRun::query()->count())->toBe(0);
});
