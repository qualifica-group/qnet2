<?php

use App\Migrations\Sources\ProductsSource;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaBusinessFunctionLinkSeeder;
use Database\Seeders\QualificaCatalog\BusinessFunctionCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Formazione" root is assigned to the business function of the same name,
// "APL" to "Agenzia per il Lavoro" (user decision 2026-10-19), "Presa
// Appuntamenti" to "Consulenza" (user directive 2026-09-28) and "No categoria" to "No funzione aziendale" (user decision
// 2026-10-12). Every one of those functions is created by the seeder when no
// function of that name exists, compared without case (user directive
// 2026-10-14): the legacy import adopts them instead of supplying them.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

$formazione = fn (): ProductCategory => ProductCategory::query()
    ->whereNull('parent_id')
    ->where('name', 'Formazione')
    ->firstOrFail();

$apl = fn (): ProductCategory => ProductCategory::query()->where('name', 'APL')->firstOrFail();

it('REQUIREMENT CHANGED (user decision 2026-10-19): links the roots to the organisation chart functions, creating only the ones outside it, idempotently', function () use ($formazione, $apl): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class); // re-run: neither duplicated nor relinked.

    expect(BusinessFunction::query()->orderBy('id')->pluck('name')->all())
        ->toBe([...array_keys(BusinessFunctionCatalogue::FUNCTIONS), 'Consulenza', 'No funzione aziendale'])
        ->and($formazione()->businessFunction->name)->toBe('Formazione')
        ->and($apl()->businessFunction->name)->toBe(BusinessFunctionCatalogue::EMPLOYMENT_AGENCY)
        // The sibling subcategories and the Consulenza root resolve nothing of their own.
        ->and(ProductCategory::query()->where('name', 'Orientamento specialistico')->value('business_function_id'))->toBeNull()
        ->and(ProductCategory::query()->whereNull('parent_id')->where('name', 'Consulenza')->value('business_function_id'))->toBeNull();
});

it('reuses a function of the same name in another case, instead of creating a second one', function () use ($formazione): void {
    $imported = BusinessFunction::factory()->create(['name' => 'FORMAZIONE']);

    test()->seed(QualificaCatalogSeeder::class);

    expect(BusinessFunction::query()->whereRaw('LOWER(name) = ?', ['formazione'])->count())->toBe(1)
        ->and($formazione()->business_function_id)->toBe($imported->id);
});

it('assigns the root, so the whole Formazione branch inherits the function', function () use ($formazione): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The link sits on the root alone: descendants resolve it own-or-inherited
    // (CategoryHierarchy::effectiveBusinessFunction), they do not carry a row.
    // "APL", "Presa Appuntamenti", "No categoria" and "SOA" (REQUIREMENT
    // CHANGED, 2026-10-15) carry their own: they are outside the Formazione branch.
    // So do "Sicurezza", "Gare", "GDPR", "Finanza Agevolata", "Istruzione" and "Accreditamenti" (REQUIREMENT CHANGED, 2026-10-20).
    expect(ProductCategory::query()->whereNotNull('business_function_id')->orderBy('id')->pluck('name')->all())
        ->toBe(['Formazione', 'Presa Appuntamenti', 'APL', 'No categoria', 'SOA', 'Sicurezza', 'Gare', 'GDPR', 'Finanza Agevolata', 'Istruzione', 'Accreditamenti'])
        ->and($formazione()->businessFunction->name)->toBe('Formazione');
});

it('never steals a slot already assigned by hand', function () use ($formazione): void {
    test()->seed(QualificaCatalogSeeder::class);

    $manual = BusinessFunction::factory()->create(['name' => 'Area Didattica']);
    $formazione()->update(['business_function_id' => $manual->id]);

    BusinessFunction::factory()->create(['name' => 'Formazione']);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class);

    expect($formazione()->business_function_id)->toBe($manual->id);
});

it('picks the lowest id when the name exists twice', function () use ($formazione): void {
    // `business_functions.name` carries no unique index: a legacy catalogue may
    // well hold the name more than once.
    $first = BusinessFunction::factory()->create(['name' => 'Formazione']);
    BusinessFunction::factory()->create(['name' => 'Formazione']);

    test()->seed(QualificaCatalogSeeder::class);

    expect($formazione()->business_function_id)->toBe($first->id);
});

it('creates the "Consulenza" function once and links "Presa Appuntamenti" to it, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class); // re-run: neither duplicated nor relinked.

    $function = BusinessFunction::query()->where('name', 'Consulenza')->sole();

    expect(ProductCategory::query()->where('name', 'Presa Appuntamenti')->value('business_function_id'))->toBe($function->id)
        ->and(ProductCategory::query()->whereNull('parent_id')->where('name', 'Consulenza')->value('business_function_id'))
        ->toBeNull();
});

it('adopts a "Consulenza" function that already exists instead of creating a second one', function (): void {
    $existing = BusinessFunction::factory()->create(['name' => 'Consulenza']);

    test()->seed(QualificaCatalogSeeder::class);

    expect(BusinessFunction::query()->where('name', 'Consulenza')->count())->toBe(1)
        ->and(ProductCategory::query()->where('name', 'Presa Appuntamenti')->value('business_function_id'))->toBe($existing->id);
});

it('leaves "Presa Appuntamenti" inheriting when its branch already carries a function', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $presaAppuntamenti = ProductCategory::query()->where('name', 'Presa Appuntamenti')->firstOrFail();
    $presaAppuntamenti->update(['business_function_id' => null]);

    // One function per root-to-leaf chain (spec 0023): the root's wins.
    $rootFunction = BusinessFunction::factory()->create(['name' => 'Area Consulenza']);
    ProductCategory::query()->whereKey($presaAppuntamenti->parent_id)->update(['business_function_id' => $rootFunction->id]);

    test()->seed(QualificaBusinessFunctionLinkSeeder::class);

    expect($presaAppuntamenti->fresh()->business_function_id)->toBeNull();
});

it('creates the "No funzione aziendale" function once and links "No categoria" to it, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class); // re-run: neither duplicated nor relinked.

    $function = BusinessFunction::query()->where('name', 'No funzione aziendale')->sole();
    $uncategorized = ProductCategory::query()->where('name', ProductsSource::UNCATEGORIZED_CATEGORY)->sole();

    // The holding root of the legacy products without a category now resolves
    // a function, so an offer line on one of them can extend the coverage of
    // its opportunity (OpportunityProductLineCoverage) instead of failing.
    expect($uncategorized->business_function_id)->toBe($function->id)
        ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($uncategorized)['id'] ?? null)->toBe($function->id);
});
