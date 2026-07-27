<?php

use App\Enums\MigrationStatus;
use App\Models\Attribute;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\QualificaLegacyImportSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 * Every legacy catalogue empty except the ones under test: `tags` (a
 * legacy-only row), `vat-rates` (a plain settings lookup), `sources` (one name
 * the static template already ships + one it does not), `attributes` and
 * `product-categories` (a legacy root, its child, and the attribute links the
 * phase-5 pass back-fills off the SAME endpoint). Specific patterns first:
 * Http::fake matches in declaration order.
 */
function fakeLegacyCatalogues(): void
{
    Http::fake([
        fakeMigrationsBaseUrl().'/tags*' => Http::response([
            'items' => [['id' => 71, 'name' => 'Legacy Tag']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/vat-rates*' => Http::response([
            'items' => [['id' => 61, 'name' => 'IVA 22%', 'rate' => 22]],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/sources*' => Http::response([
            'items' => [
                ['id' => 81, 'name' => 'Passaparola'],
                ['id' => 82, 'name' => 'Fiera'],
            ],
            'pagination' => ['total' => 2],
        ]),
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [['id' => 91, 'code' => 'durata', 'name' => 'Durata', 'type' => 'decimal']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 51, 'name' => 'Bandi', 'parent_id' => null, 'attributes' => [
                    ['attribute_id' => 91, 'context' => 'product'],
                ]],
                ['id' => 52, 'name' => 'Bandi Regionali', 'parent_id' => 51],
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

it('imports the legacy vat rates as part of the fixed source list', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: skipped by old_id, never duplicated.

    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('vat-rates')
        ->and(VatRate::query()->where('old_id', 61)->count())->toBe(1)
        ->and(VatRate::query()->where('old_id', 61)->value('name'))->toBe('IVA 22%')
        ->and((float) VatRate::query()->where('old_id', 61)->value('rate'))->toBe(22.0);
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

it('nests the imported product taxonomy under the Consulenza root, keeping its own hierarchy', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    test()->seed(QualificaTemplateSeeder::class);

    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->sole();
    $legacyRoot = ProductCategory::query()->where('old_id', 51)->sole();
    $legacyChild = ProductCategory::query()->where('old_id', 52)->sole();

    expect($legacyRoot->parent_id)->toBe($consulenza->id)
        // The legacy hierarchy survives: only top-level nodes are reparented.
        ->and($legacyChild->parent_id)->toBe($legacyRoot->id)
        // The static template's own tree keeps its shape.
        ->and(ProductCategory::query()->where('name', 'Formazione')->value('parent_id'))->toBeNull()
        ->and(ProductCategory::query()->where('name', 'Trattative in Corso')->value('parent_id'))->toBe($consulenza->id);
});

it('links the imported attributes onto the imported category in the declared context', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    test()->seed(QualificaTemplateSeeder::class);

    $attribute = Attribute::query()->where('old_id', 91)->sole();
    $category = ProductCategory::query()->where('old_id', 51)->sole();

    $links = DB::table('attribute_category')->where('category_id', $category->id)->get();

    expect($attribute->code)->toBe('durata')
        ->and($links)->toHaveCount(1)
        ->and($links[0]->attribute_id)->toBe($attribute->id)
        ->and($links[0]->context)->toBe('product');
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
        ->and(ProductCategory::query()->where('name', 'Bandi')->count())->toBe(1)
        // Already nested by the first run: the second one moves nothing.
        ->and(ProductCategory::query()->where('old_id', 51)->value('parent_id'))
        ->toBe(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('id'))
        ->and(DB::table('attribute_category')->count())->toBe(1)
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
