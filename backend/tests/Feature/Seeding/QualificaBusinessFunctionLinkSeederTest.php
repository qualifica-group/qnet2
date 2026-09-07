<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use Database\Seeders\QualificaBusinessFunctionLinkSeeder;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Formazione" root category and the "APL" subcategory are assigned to the
// business function of the same name — functions the external qnet CRM
// supplies, so the link runs after the import and is never fatal when one of
// them is missing.
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

it('leaves the categories unassigned, without failing, when the functions are absent', function () use ($formazione, $apl): void {
    // No import ran: the legacy business functions do not exist.
    test()->seed(QualificaCatalogSeeder::class);

    expect(BusinessFunction::query()->count())->toBe(0)
        ->and($formazione()->business_function_id)->toBeNull()
        ->and($apl()->business_function_id)->toBeNull();
});

it('assigns the root once the imported function exists, idempotently', function () use ($formazione): void {
    test()->seed(QualificaCatalogSeeder::class);

    $function = BusinessFunction::factory()->create(['name' => 'Formazione']);

    test()->seed(QualificaBusinessFunctionLinkSeeder::class);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class); // re-run: already linked, no change.

    expect($formazione()->business_function_id)->toBe($function->id);
});

it('assigns the root, so the whole Formazione branch inherits the function', function () use ($formazione): void {
    test()->seed(QualificaCatalogSeeder::class);

    BusinessFunction::factory()->create(['name' => 'Formazione']);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class);

    // The link sits on the root alone: descendants resolve it own-or-inherited
    // (CategoryHierarchy::effectiveBusinessFunction), they do not carry a row.
    expect(ProductCategory::query()->whereNotNull('business_function_id')->pluck('name')->all())
        ->toBe(['Formazione'])
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

it('picks the lowest id when the legacy catalogue holds the name twice', function () use ($formazione): void {
    test()->seed(QualificaCatalogSeeder::class);

    // `business_functions.name` carries no unique index: a legacy catalogue may
    // well hold the name more than once.
    $first = BusinessFunction::factory()->create(['name' => 'Formazione']);
    BusinessFunction::factory()->create(['name' => 'Formazione']);

    test()->seed(QualificaBusinessFunctionLinkSeeder::class);

    expect($formazione()->business_function_id)->toBe($first->id);
});

it('assigns the "APL" root to the function of the same name, idempotently', function () use ($apl): void {
    test()->seed(QualificaCatalogSeeder::class);

    $function = BusinessFunction::factory()->create(['name' => 'APL']);

    test()->seed(QualificaBusinessFunctionLinkSeeder::class);
    test()->seed(QualificaBusinessFunctionLinkSeeder::class); // re-run: already linked, no change.

    // The row sits on the APL root; its child carries none — it resolves the
    // function own-or-inherited — and no other branch is touched.
    expect($apl()->business_function_id)->toBe($function->id)
        ->and($apl()->businessFunction->name)->toBe('APL')
        ->and(ProductCategory::query()->where('name', 'Orientamento Specialistico')->value('business_function_id'))
        ->toBeNull()
        ->and(ProductCategory::query()->whereNull('parent_id')->where('name', 'Consulenza')->value('business_function_id'))
        ->toBeNull();
});

it('links each category independently, so a missing function never blocks the other', function () use ($formazione, $apl): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Only one of the two functions made it through the legacy import.
    $function = BusinessFunction::factory()->create(['name' => 'APL']);

    test()->seed(QualificaBusinessFunctionLinkSeeder::class);

    expect($apl()->business_function_id)->toBe($function->id)
        ->and($formazione()->business_function_id)->toBeNull();
});
