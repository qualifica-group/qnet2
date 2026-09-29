<?php

use App\Models\BusinessFunction;
use App\Models\CustomFieldDefinition;
use App\Models\Lead;
use App\Models\MassMigrationRun;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaProductionDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// The single entry point for the client's production-like dataset. Each step
// is covered by its own suite (QualificaTemplateSeederTest,
// QualificaCatalogSeederTest, TestUsersSeederTest, QualificaOperatorSeederTest,
// QualificaLegacyImportSeederTest); what is pinned HERE is that they run
// together, in the order their dependencies require — and, since the user
// directive 2026-09-08, that the chain produces NO fabricated row: the sample
// pipeline is QualificaSampleDataSeeder's own business now.
//
// The seeder itself is the expensive part of this suite, so tests that share
// an identical setup (same pre-state, same seed sequence) are merged into one
// scenario, carrying all of their original assertions.
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

/**
 * Merged scenario (seeder cost): three former tests that share the exact same
 * setup — an operational site standing in for the (here no-op) legacy import,
 * one seed(QualificaProductionDataSeeder::class) run, then only reads — so
 * they pay the seeder once instead of three times.
 */
it('composes structure, catalogue and operators in one run, with a super-admin and the operators\' sites in place', function (): void {
    // was: 'gives the operators their operational sites, so they are selectable as operators'
    // The sites are imported by the legacy step, which is a no-op here: stand
    // one in first, exactly as the import would have left it.
    $site = OperationalSite::factory()->create(['alias' => 'FRATTAMAGGIORE 1 (HQ)']);

    test()->seed(QualificaProductionDataSeeder::class);

    // was: 'composes structure, catalogue and operators in one run'
    expect(CustomFieldDefinition::query()->where('entity_type', 'company-sites')->count())->toBe(36)
        ->and(CustomFieldDefinition::query()->where('entity_type', 'products')->count())->toBe(2)
        ->and(Source::query()->where('name', 'Passaparola')->count())->toBe(1)
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->count())->toBe(1)
        ->and(Product::query()->count())->toBe(303)
        // Before the legacy import: its products are filed by typology NAME.
        ->and(ProductTypology::query()->orderBy('name')->pluck('name')->all())->toBe(['Consulenza', 'Ente'])
        ->and(User::query()->where('email', 'ciro.cacciapuoti@qualificagroup.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->exists())->toBeTrue()
        // Step 8 runs after step 7: the staff lands, the roster keeps its mansione.
        ->and(User::query()->where('email', 'nicola.eliseo@qualificagroup.com')->sole()->getRoleNames()->all())->toBe([OperatorRoleCatalogue::BASE_ROLE])
        ->and(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->sole()->getRoleNames()->all())->toBe([OperatorRoleCatalogue::SUPERVISOR_ROLE])
        // Step 9 runs last: a staff account reports to the operators of step 7.
        ->and(User::query()->where('email', 'jessica.virgolini@qualificagroup.com')->with('employment.reportsTo')->sole()
            ->employment->reportsTo->pluck('email')->all())->toBe(['fabrizio.aliberti@qualificagroup.com', 'rosa.falzarano@qualificagroup.com']);

    // was: 'seeds the super-admin before the legacy import, so an actor always exists'
    // The import runs on behalf of a super-admin; TestUsersSeeder is the step
    // that guarantees one, which is why it precedes it.
    expect(User::query()->whereHas('roles', fn ($query) => $query->where('name', UserService::PRIVILEGED_ROLE))->exists())
        ->toBeTrue();

    // was: 'gives the operators their operational sites, so they are selectable as operators'
    // Spec 0103: the Operatore select filters users on this very pivot
    // membership, so an account without an employment profile never appears
    // in the list.
    $employment = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')
        ->with('employment.operationalSites')->firstOrFail()->employment;

    expect($employment->primaryOperationalSiteId)->toBe($site->getKey());
});

/**
 * Merged scenario (seeder cost): two former tests that share the exact same
 * pre-state — a business function standing in for the (here no-op) legacy
 * import — one carrying its assertions after the first run, the other after
 * a re-run, exactly as each originally did.
 */
it('seeds no fabricated row on the first run, and duplicates nothing on a re-run', function (): void {
    // User directive 2026-09-08: the two *Sample* steps left this chain for
    // QualificaSampleDataSeeder. Standing in the business function the import
    // would have brought is what USED to make them seed — with it present and
    // the grids still empty, the split is pinned, not merely untested.
    BusinessFunction::factory()->create(['name' => 'Formazione']);

    test()->seed(QualificaProductionDataSeeder::class);

    // was: 'seeds no fabricated row: the sample pipeline is a separate entry point'
    expect(Lead::query()->count())->toBe(0)
        ->and(Opportunity::query()->count())->toBe(0)
        ->and(Quote::query()->count())->toBe(0);

    // was: 'is idempotent: a second run duplicates nothing'
    test()->seed(QualificaProductionDataSeeder::class);

    expect(Source::query()->count())->toBe(10)
        ->and(Product::query()->count())->toBe(303)
        // Before the legacy import: its products are filed by typology NAME.
        ->and(ProductTypology::query()->orderBy('name')->pluck('name')->all())->toBe(['Consulenza', 'Ente'])
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
