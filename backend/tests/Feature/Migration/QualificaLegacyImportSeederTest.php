<?php

use App\Enums\MigrationStatus;
use App\Enums\ProductUsage;
use App\Migrations\Support\LegacyAplBranch;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Source;
use App\Models\Tag;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaLegacyImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor) are defined once, guarded by function_exists,
// across the Migration feature suite (see CompaniesSourceImportTest).

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
 * the static catalogue already ships + one it does not), `attributes` and
 * `product-categories` (a legacy root, its child, and the attribute links the
 * phase-5 pass back-fills off the SAME endpoint), plus `payment-methods` and
 * the `companies`/`company-sites` pair that proves the phase-2 remap runs
 * inside this seed. Specific patterns first: Http::fake matches in declaration
 * order, so the catch-all stays last.
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
        fakeMigrationsBaseUrl().'/payment-methods*' => Http::response([
            'items' => [['id' => 41, 'name' => 'Bonifico bancario', 'code' => 'bank_transfer', 'payment_days' => 30]],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/task-templates*' => Http::response([
            'items' => [['id' => 47, 'name' => 'ISO_Modello Iso_rev. 1_ (Attivo)', 'stages' => [
                ['id' => 323, 'name' => 'LAVORAZIONE', 'position' => 1, 'items' => [
                    ['id' => 392, 'parent_id' => null, 'title' => 'Primo contatto', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 1],
                    ['id' => 393, 'parent_id' => 392, 'title' => 'Invio email', 'description' => null, 'estimated_hours' => null, 'estimated_minutes' => null, 'position' => 2],
                ]],
            ]]],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/company-sites*' => Http::response([
            'items' => [['id' => 31, 'company_id' => 21, 'name' => 'Sede di Melfi']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [['id' => 21, 'denomination' => 'Lucania Srl']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [['id' => 91, 'code' => 'durata', 'name' => 'Durata', 'type' => 'decimal']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 51, 'name' => 'Bandi', 'parent_id' => null, 'attributes' => [
                    ['attribute_id' => 91, 'context' => 'product'],
                    ['attribute_id' => 91, 'context' => 'work_order'],
                ]],
                ['id' => 52, 'name' => 'Bandi Regionali', 'parent_id' => 51],
            ],
            'pagination' => ['total' => 2],
        ]),
        fakeMigrationsBaseUrl().'/attribute-layouts*' => Http::response([
            'items' => [[
                'id' => '51-work_order-all', 'category_id' => 51, 'context' => 'work_order', 'form_mode' => 'all',
                'layout' => ['sections' => [[
                    'id' => 'main', 'title' => 'Main', 'description' => null, 'variant' => 'default',
                    'collapsible' => false, 'default_collapsed' => false, 'columns' => 2, 'sort_order' => 0,
                    'rows' => [['id' => 'row-1', 'items' => [['attribute_code' => 'durata', 'width' => 'full']]]],
                ]]],
            ]],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [[
                'id' => 101, 'name' => 'Bando Sviluppo Impresa', 'price' => 1200, 'cost' => 300,
                'category_id' => 52, 'vat_rate_id' => 61, 'product_type' => 'service',
            ]],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/cost-products*' => Http::response([
            'items' => [[
                'id' => 'vehicles:3', 'source' => 'vehicles', 'source_id' => 3, 'name' => 'Tagliando Smart',
                'cost' => 140, 'price' => 140, 'vat_rate_id' => 61, 'license_plate' => 'FE700FV',
            ]],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/*' => Http::response(['items' => [], 'pagination' => ['total' => 0]]),
    ]);
}

/**
 * The documented run order: QualificaLegacyImportSeeder runs AFTER
 * QualificaCatalogSeeder, which owns the "Consulenza" root it nests under and
 * the static source catalogue it must adopt. QualificaTemplateSeeder (custom
 * field structure) plays no part here — hence its absence.
 */
function seedCatalogThenLegacy(): void
{
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaLegacyImportSeeder::class);
}

/**
 * Merged scenario (seeder cost): eight former tests that all share the exact
 * same setup — seedMigrationsConfig() + a super-admin actor + the same fixed
 * fakeLegacyCatalogues() payload — differing only in whether they read after
 * ONE run or after a RE-RUN (idempotency). They now pay that setup once
 * (single run) plus one re-run, instead of twelve seeder passes.
 */
it('imports the fixed legacy source list as one mass run, mirrored across every catalogue it touches, idempotently', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();
    fakeLegacyCatalogues();

    seedCatalogThenLegacy();
    // Read before the re-run below, exactly where the merged 're-running...'
    // scenario originally took its own "before" snapshot.
    $sourceCountAfterFirst = Source::query()->count();

    // was: 'runs the fixed source list as one inline mass run and completes it'
    $massRun = MassMigrationRun::query()->sole();

    expect($massRun->sources)->toBe(QualificaLegacyImportSeeder::SOURCES)
        ->and($massRun->status)->toBe(MigrationStatus::Completed)
        ->and($massRun->runs()->count())->toBe(count(QualificaLegacyImportSeeder::SOURCES))
        // Child runs created in plan order: the phase order is the contract.
        ->and(MigrationRun::query()->orderBy('id')->pluck('source')->all())->toBe(QualificaLegacyImportSeeder::SOURCES)
        ->and(Tag::query()->where('old_id', 71)->value('name'))->toBe('Legacy Tag');

    // was: 'adopts a catalogue source instead of duplicating it, and imports the legacy-only one'
    expect(Source::query()->where('name', 'Passaparola')->count())->toBe(1)
        ->and(Source::query()->where('name', 'Passaparola')->value('old_id'))->toBe(81)
        ->and(Source::query()->where('name', 'Fiera')->value('old_id'))->toBe(82);

    // was: 'nests the imported product taxonomy under the Consulenza root, keeping its own hierarchy'
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->sole();
    $legacyRoot = ProductCategory::query()->where('old_id', 51)->sole();
    $legacyChild = ProductCategory::query()->where('old_id', 52)->sole();

    expect($legacyRoot->parent_id)->toBe($consulenza->id)
        // The legacy hierarchy survives: only top-level nodes are reparented.
        ->and($legacyChild->parent_id)->toBe($legacyRoot->id)
        // The static catalogue's own tree keeps its shape.
        ->and(ProductCategory::query()->where('name', 'Formazione')->value('parent_id'))->toBeNull()
        ->and(ProductCategory::query()->where('name', 'Presa Appuntamenti')->value('parent_id'))->toBe($consulenza->id);

    // was: 'links the imported attributes onto the imported category in the declared context'
    $importedAttribute = Attribute::query()->where('old_id', 91)->sole();
    $importedCategory = ProductCategory::query()->where('old_id', 51)->sole();

    $attributeLinks = DB::table('attribute_category')->where('category_id', $importedCategory->id)->get();

    expect($importedAttribute->code)->toBe('durata')
        ->and($attributeLinks)->toHaveCount(2)
        ->and($attributeLinks[0]->attribute_id)->toBe($importedAttribute->id)
        ->and($attributeLinks->pluck('context')->sort()->values()->all())->toBe(['product', 'work_order']);

    // Spec 0181: the layout lands after the links it references, once.
    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('attribute-layouts')
        ->and(AttributeLayout::query()->where('product_category_id', $importedCategory->id)->sole()->layout['sections'][0]['title'])->toBe('Main');

    seedCatalogThenLegacy(); // re-run: skipped by old_id, never duplicated — shared by every block below.

    // was: 'imports the legacy vat rates as part of the fixed source list'
    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('vat-rates')
        ->and(VatRate::query()->where('old_id', 61)->count())->toBe(1)
        ->and(VatRate::query()->where('old_id', 61)->value('name'))->toBe('IVA 22%')
        ->and((float) VatRate::query()->where('old_id', 61)->value('rate'))->toBe(22.0);

    // was: 'imports the legacy payment methods as part of the fixed source list'
    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('payment-methods')
        ->and(PaymentMethod::query()->where('old_id', 41)->count())->toBe(1)
        ->and(PaymentMethod::query()->where('old_id', 41)->value('name'))->toBe('Bonifico bancario')
        ->and(PaymentMethod::query()->where('old_id', 41)->value('payment_days'))->toBe(30);

    // was: 'imports the legacy company sites linked to their imported company'
    $legacySite = CompanySite::query()->where('old_id', 31)->sole();

    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('company-sites')
        ->and($legacySite->name)->toBe('Sede di Melfi')
        // Phase 2 runs after phase 1 in this seed: the company_id is remapped
        // onto the company the SAME run imported, not left unlinked.
        ->and($legacySite->company_id)->toBe(Company::query()->where('old_id', 21)->value('id'));

    // Spec 0172: the legacy task templates land with their stage and sub-task
    // tree, once — the re-run above skipped the model by its old_id.
    $taskTemplate = TaskTemplate::query()->where('old_id', 47)->sole();
    $rootItem = $taskTemplate->items()->where('title', 'Primo contatto')->sole();
    $subItem = $taskTemplate->items()->where('title', 'Invio email')->sole();

    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('task-templates')
        ->and($taskTemplate->is_active)->toBeTrue()
        ->and($taskTemplate->stages()->pluck('name')->all())->toBe(['LAVORAZIONE'])
        ->and($rootItem->task_template_stage_id)->toBe($taskTemplate->stages()->value('id'))
        ->and($subItem->parent_id)->toBe($rootItem->id)
        ->and($subItem->task_template_stage_id)->toBeNull();

    // Spec 0174: the legacy costs land once, cost-only, under the "Costi"
    // root, which stays at top level (no old_id, never nested).
    $costRoot = ProductCategory::query()->where('name', 'Costi')->whereNull('parent_id')->sole();
    $vehicle = Product::query()->where('old_source', 'vehicles')->where('old_id', 3)->sole();

    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('cost-products')
        ->and($vehicle->category_id)->toBe(ProductCategory::query()->where('name', 'Veicoli')->where('parent_id', $costRoot->id)->value('id'))
        ->and($vehicle->usages->all())->toBe([ProductUsage::Cost])
        ->and($vehicle->vat_rate_id)->toBe(VatRate::query()->where('old_id', 61)->value('id'))
        ->and($vehicle->attribute_values)->toBe(['cost_license_plate' => 'FE700FV']);

    // User directive 2026-09-29: the sellable catalogue lands once, filed on
    // its migrated category with its migrated VAT rate.
    $service = Product::query()->where('old_source', 'services')->where('old_id', 101)->sole();

    expect(QualificaLegacyImportSeeder::SOURCES)->toContain('products')
        ->and($service->name)->toBe('Bando Sviluppo Impresa')
        ->and($service->category_id)->toBe($legacyChild->id)
        ->and($service->vat_rate_id)->toBe(VatRate::query()->where('old_id', 61)->value('id'));

    // was: 're-running the seeders never duplicates an imported catalogue'
    expect(Source::query()->count())->toBe($sourceCountAfterFirst)
        ->and(Tag::query()->where('name', 'Legacy Tag')->count())->toBe(1)
        ->and(ProductCategory::query()->where('name', 'Bandi')->count())->toBe(1)
        ->and(Product::query()->where('old_source', 'vehicles')->count())->toBe(1)
        // Already nested by the first run: the second one moves nothing.
        ->and(ProductCategory::query()->where('old_id', 51)->value('parent_id'))
        ->toBe(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('id'))
        // Scoped to the imported category: the catalogue itself now owns an
        // unrelated pivot row (the "Ore complessive" attribute on Formazione),
        // so a global count no longer isolates the legacy import.
        ->and(DB::table('attribute_category')->where('category_id', ProductCategory::query()->where('old_id', 51)->value('id'))->count())->toBe(2)
        ->and(AttributeLayout::query()->where('product_category_id', ProductCategory::query()->where('old_id', 51)->value('id'))->count())->toBe(1)
        ->and(MassMigrationRun::query()->count())->toBe(2)
        ->and(MassMigrationRun::query()->latest('id')->first()->status)->toBe(MigrationStatus::Completed);
});

it('adopts the static "Formazione" root, and nests the legacy APL tree under "Consulenza" as "APL old"', function () {
    seedMigrationsConfig();
    migrationsSuperAdminActor();

    // The legacy catalogue repeats two ROOT names the static one ships,
    // "Formazione" and "APL", and the APL practices under the latter.
    Http::fake([
        fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
            'items' => [['id' => 9, 'name' => 'APL']],
            'pagination' => ['total' => 1],
        ]),
        fakeMigrationsBaseUrl().'/product-categories*' => Http::response([
            'items' => [
                ['id' => 55, 'name' => 'Formazione', 'parent_id' => null],
                ['id' => 56, 'name' => 'APL', 'parent_id' => null, 'business_function_id' => 9],
                ['id' => 57, 'name' => 'Orientamento Specialistico', 'parent_id' => 56, 'business_function_id' => 9],
                ['id' => 58, 'name' => 'Tirocini extracurriculari privati', 'parent_id' => 56, 'business_function_id' => 9],
                ['id' => 59, 'name' => 'Formazione Apprendistato', 'parent_id' => 56, 'business_function_id' => 9],
                ['id' => 60, 'name' => 'Ricerca e Selezione', 'parent_id' => 56, 'business_function_id' => 9],
            ],
            'pagination' => ['total' => 7],
        ]),
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => collect([201 => 57, 202 => 58, 203 => 59, 204 => 60])
                ->map(fn (int $categoryId, int $id) => ['id' => $id, 'name' => "Legacy {$id}", 'category_id' => $categoryId, 'product_type' => 'service'])
                ->values()->all(),
            'pagination' => ['total' => 4],
        ]),
        fakeMigrationsBaseUrl().'/*' => Http::response(['items' => [], 'pagination' => ['total' => 0]]),
    ]);

    seedCatalogThenLegacy();

    $formazione = ProductCategory::query()->where('name', 'Formazione')->sole();
    $manualApl = ProductCategory::query()->where('name', 'APL')->sole();
    $legacyApl = ProductCategory::query()->where('name', LegacyAplBranch::LEGACY_BRANCH)->sole();
    $categoryOf = fn (int $oldId) => Product::query()->where('old_id', $oldId)->first()->category->name;

    // "Formazione" adopted, not duplicated, and never moved: dragging it
    // under "Consulenza" would take the whole GOL branch with it.
    expect($formazione->old_id)->toEqual(55)
        ->and($formazione->parent_id)->toBeNull()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('parent_id'))->toBe($formazione->id)
        // The manual APL branch adopts nothing (user directive 2026-10-05)...
        ->and($manualApl->old_id)->toBeNull()
        ->and($manualApl->parent_id)->toBeNull()
        ->and(ProductCategory::query()->where('parent_id', $manualApl->id)->whereNotNull('old_id')->exists())->toBeFalse()
        // ...its legacy twin is nested under "Consulenza" like every legacy
        // root (as the FORMAZIONE OLD categories are), on a function of its own...
        ->and($legacyApl->old_id)->toEqual(56)
        ->and($legacyApl->parent->name)->toBe('Consulenza')
        ->and($legacyApl->businessFunction->name)->toBe('APL OLD')
        // ...and is never a classification target, unlike the manual one.
        ->and(ProductCategory::query()->whereKey([$legacyApl->id, ...$legacyApl->children()->pluck('id')])->where('is_selectable', true)->exists())->toBeFalse()
        ->and(ProductCategory::query()->where('parent_id', $manualApl->id)->where('is_selectable', false)->exists())->toBeFalse()
        ->and(ProductCategory::query()->where('parent_id', $legacyApl->id)->orderBy('name')->pluck('name')->all())
        ->toBe(['Formazione Apprendistato', 'Orientamento Specialistico old', 'Ricerca e Selezione', 'Tirocini extracurriculari privati'])
        // ...and its products land on the manual categories replacing the
        // legacy ones (LegacyAplBranch::PRODUCT_CATEGORIES, bound here to the
        // real catalogue), save the one with no replacement.
        ->and($categoryOf(201))->toBe('Orientamento specialistico')
        ->and($categoryOf(202))->toBe('Tirocinio')
        ->and($categoryOf(203))->toBe('Apprendistato')
        ->and($categoryOf(204))->toBe('Ricerca e Selezione');

    foreach (LegacyAplBranch::PRODUCT_CATEGORIES as $manualName) {
        expect(ProductCategory::query()->where('name', $manualName)->value('parent_id'))->toBe($manualApl->id);
    }
});

it('skips the import when no external system is configured', function () {
    config(['migrations.base_url' => null]);
    migrationsSuperAdminActor();
    Http::preventStrayRequests();

    seedCatalogThenLegacy();

    // The static catalogue still lands; only the legacy step is skipped.
    expect(MassMigrationRun::query()->count())->toBe(0)
        ->and(Source::query()->where('name', 'Passaparola')->count())->toBe(1);
});

it('skips the import when no super-admin exists to run it as', function () {
    seedMigrationsConfig();
    Http::preventStrayRequests();

    seedCatalogThenLegacy();

    expect(MassMigrationRun::query()->count())->toBe(0);
});
