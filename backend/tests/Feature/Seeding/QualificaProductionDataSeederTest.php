<?php

use App\Models\CustomFieldDefinition;
use App\Models\MassMigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Source;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\QualificaProductionDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// The single entry point for the client's production-like dataset. Each step
// is covered by its own suite (QualificaTemplateSeederTest,
// QualificaCatalogSeederTest, TestUsersSeederTest,
// QualificaLegacyImportSeederTest); what is pinned HERE is that the four run
// together, in the order their dependencies require.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // No external system configured: the legacy step is a documented no-op,
    // so the seeder is exercised without reaching the network.
    config(['migrations.base_url' => null]);
    Http::preventStrayRequests();
    // Step 1's layout uploads the client letterhead: keep the binary off the
    // real disk.
    Storage::fake(config('attachments.disk'));
});

it('composes structure, catalogue and testers in one run', function (): void {
    test()->seed(QualificaProductionDataSeeder::class);

    expect(CustomFieldDefinition::query()->where('entity_type', 'company-sites')->count())->toBe(36)
        ->and(CustomFieldDefinition::query()->where('entity_type', 'products')->count())->toBe(2)
        ->and(Source::query()->where('name', 'Passaparola')->count())->toBe(1)
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->count())->toBe(1)
        ->and(Product::query()->count())->toBe(262)
        ->and(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->exists())->toBeTrue();
});

it('seeds the testers before the legacy import, so an actor always exists', function (): void {
    test()->seed(QualificaProductionDataSeeder::class);

    // The import runs on behalf of a super-admin; TestUsersSeeder is the step
    // that guarantees one, which is why it precedes it.
    expect(User::query()->whereHas('roles', fn ($query) => $query->where('name', UserService::PRIVILEGED_ROLE))->exists())
        ->toBeTrue();
});

it('is idempotent: a second run duplicates nothing', function (): void {
    test()->seed(QualificaProductionDataSeeder::class);
    test()->seed(QualificaProductionDataSeeder::class);

    expect(Source::query()->count())->toBe(10)
        ->and(Product::query()->count())->toBe(262)
        ->and(ProductCategory::query()->where('name', 'Formazione')->count())->toBe(1)
        ->and(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->count())->toBe(1);
});

it('runs the q-crm import once, without the catalogue step asking again', function (): void {
    config(['migrations.base_url' => 'https://q-crm.test']);
    Http::fake(['https://q-crm.test/*' => Http::response(['items' => [], 'pagination' => ['total' => 0]])]);

    // Driven through artisan (not test()->seed()) so the run IS interactive:
    // the catalogue step asking here would surface as an unexpected question.
    test()->artisan('db:seed', ['--class' => QualificaProductionDataSeeder::class])->assertSuccessful();

    expect(MassMigrationRun::query()->count())->toBe(1);
});
