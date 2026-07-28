<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use Database\Seeders\QualificaBusinessFunctionLinkSeeder;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Formazione" root category is assigned to the business function of the
// same name — a function the external qnet CRM supplies, so the link runs after
// the import and is never fatal when that function is missing.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

$formazione = fn (): ProductCategory => ProductCategory::query()
    ->whereNull('parent_id')
    ->where('name', 'Formazione')
    ->firstOrFail();

it('leaves the category unassigned, without failing, when the function is absent', function () use ($formazione): void {
    // No import ran: the legacy business functions do not exist.
    test()->seed(QualificaCatalogSeeder::class);

    expect(BusinessFunction::query()->count())->toBe(0)
        ->and($formazione()->business_function_id)->toBeNull();
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
