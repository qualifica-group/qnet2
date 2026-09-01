<?php

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryManagerLabelResolver;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The ROOT-OWNED rules QualificaCatalog\CatalogRootRules declares for the two
 * catalogue roots, split out of QualificaCatalogSeederTest alongside the
 * production class itself.
 *
 * "Formazione" is worked ONE product line and ONE offer at a time (user
 * directive 2026-08-03 for the line, 2026-08-07 for the offer) and is NOT
 * sold under a contract (spec 0091, user directive 2026-09-01); "Consulenza"
 * stays unconstrained on all three. Every rule is realigned on every run and
 * cascades to the whole branch, third-level GOL regions included.
 *
 * The root also carries the four G.A. labels of a training deal (user
 * directive 2026-08-31, spec 0080). Those are not mirrored on the subtree:
 * descendants resolve them by climbing to the root.
 */
uses(RefreshDatabase::class);

it('seeds "Formazione" as single and "Consulenza" as multiple management mode, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: realigned, not duplicated.

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('management_mode'))
        ->toBe(CategoryManagementMode::Single)
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('management_mode'))
        ->toBe(CategoryManagementMode::Multiple);
});

it('cascades "single" from the Formazione root to every descendant, including the third-level GOL regions', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $descendantIds = app(CategoryHierarchy::class)->descendantIds($formazione->id);

    expect($descendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $descendantIds)->where('management_mode', '!=', CategoryManagementMode::Single)->count())
        ->toBe(0);
});

it('leaves "Consulenza" and its descendants at "multiple"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->firstOrFail();
    $descendantIds = app(CategoryHierarchy::class)->descendantIds($consulenza->id);

    expect($descendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $descendantIds)->where('management_mode', '!=', CategoryManagementMode::Multiple)->count())
        ->toBe(0);
});

it('realigns a "Formazione" branch seeded as "multiple" before this directive, cascading to its descendants', function (): void {
    // The state of an installation seeded before the "single" directive.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'management_mode' => CategoryManagementMode::Multiple]);
    $gol = ProductCategory::factory()->create(['name' => 'GOL', 'parent_id' => $formazione->id, 'management_mode' => CategoryManagementMode::Multiple]);
    ProductCategory::factory()->create(['name' => 'GOL - Molise', 'parent_id' => $gol->id, 'management_mode' => CategoryManagementMode::Multiple]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('management_mode'))
        ->toBe(CategoryManagementMode::Single)
        ->and(ProductCategory::query()->where('name', 'GOL')->value('management_mode'))->toBe(CategoryManagementMode::Single)
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('management_mode'))->toBe(CategoryManagementMode::Single);
});

it('does not touch already-aligned management_mode rows on re-run', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $updatedAt = $molise->updated_at;

    test()->seed(QualificaCatalogSeeder::class); // re-run: no spurious update.

    expect($molise->fresh()->updated_at)->toEqual($updatedAt);
});

it('seeds "Formazione" with the one-offer-per-opportunity rule and "Consulenza" without it, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: realigned, not duplicated.

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('single_quote_per_opportunity'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('single_quote_per_opportunity'))
        ->toBeFalsy();
});

it('cascades the one-offer rule from the Formazione root to every descendant, including the third-level GOL regions', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $descendantIds = app(CategoryHierarchy::class)->descendantIds($formazione->id);

    expect($descendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $descendantIds)->where('single_quote_per_opportunity', false)->count())
        ->toBe(0);
});

it('leaves "Consulenza" and its descendants without the one-offer rule', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->firstOrFail();
    $descendantIds = app(CategoryHierarchy::class)->descendantIds($consulenza->id);

    expect($descendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $descendantIds)->where('single_quote_per_opportunity', true)->count())
        ->toBe(0);
});

it('realigns a "Formazione" branch seeded before the one-offer directive, cascading to its descendants', function (): void {
    // The state of an installation seeded before the directive.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'single_quote_per_opportunity' => false]);
    $gol = ProductCategory::factory()->create(['name' => 'GOL', 'parent_id' => $formazione->id, 'single_quote_per_opportunity' => false]);
    ProductCategory::factory()->create(['name' => 'GOL - Molise', 'parent_id' => $gol->id, 'single_quote_per_opportunity' => false]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('single_quote_per_opportunity'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('single_quote_per_opportunity'))->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('single_quote_per_opportunity'))->toBeTruthy();
});

it('AC-013: seeds "Formazione" without contract generation and "Consulenza" with it, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: realigned, not duplicated.

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('generates_contract'))
        ->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('generates_contract'))
        ->toBeTruthy();
});

it('AC-013: cascades the no-contract rule from the Formazione root to every descendant, including the third-level GOL regions', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $descendantIds = app(CategoryHierarchy::class)->descendantIds($formazione->id);

    expect($descendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $descendantIds)->where('generates_contract', true)->count())
        ->toBe(0);
});

it('AC-013: leaves "Consulenza" and its descendants generating contracts', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->firstOrFail();
    $descendantIds = app(CategoryHierarchy::class)->descendantIds($consulenza->id);

    expect($descendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $descendantIds)->where('generates_contract', false)->count())
        ->toBe(0);
});

it('AC-014: realigns a "Formazione" branch seeded before the no-contract directive, cascading to its descendants', function (): void {
    // The state of an installation seeded before the directive: the column
    // default (true) is what every existing row carries.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'generates_contract' => true]);
    $gol = ProductCategory::factory()->create(['name' => 'GOL', 'parent_id' => $formazione->id, 'generates_contract' => true]);
    ProductCategory::factory()->create(['name' => 'GOL - Molise', 'parent_id' => $gol->id, 'generates_contract' => true]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('generates_contract'))
        ->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('generates_contract'))->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('generates_contract'))->toBeFalsy();
});

it('seeds the four "Formazione" G.A. labels on the root, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: realigned, not duplicated.

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    expect($formazione->manager_labels)->toBe([
        1 => 'Tutor',
        2 => 'Operatore',
        3 => 'Partner commerciale',
        4 => 'Segnalatore',
    ]);
});

it('resolves the "Formazione" G.A. labels on the whole branch, third-level GOL regions included', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    expect(app(CategoryManagerLabelResolver::class)->effectiveManagerLabels($molise))
        ->toBe([1 => 'Tutor', 2 => 'Operatore', 3 => 'Partner commerciale', 4 => 'Segnalatore']);
});

it('leaves "Consulenza" without its own G.A. labels', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('manager_labels'))
        ->toBeEmpty();
});

it('realigns a "Formazione" root seeded before the G.A. label directive', function (): void {
    // The state of an installation seeded before the directive.
    ProductCategory::factory()->create(['name' => 'Formazione', 'manager_labels' => null]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('manager_labels'))
        ->toBe([1 => 'Tutor', 2 => 'Operatore', 3 => 'Partner commerciale', 4 => 'Segnalatore']);
});
