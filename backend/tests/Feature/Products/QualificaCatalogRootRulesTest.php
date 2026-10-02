<?php

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryManagerLabelResolver;
use Database\Seeders\QualificaCatalog\ECampusCourseCatalogue;
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

/**
 * One scenario instead of sixteen: the catalogue seeder costs ~1s per run,
 * and every read-only check below used to pay for it separately — ten after
 * a single seed, five more after a repeated (idempotent) one, plus the
 * "no spurious update" timestamp check, which needs that exact two-seed
 * shape anyway.
 */
it('applies every root rule to Formazione and Consulenza, cascades it to their descendants, and never spuriously re-touches an aligned row', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $hierarchy = app(CategoryHierarchy::class);
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->firstOrFail();
    $formazioneDescendantIds = $hierarchy->descendantIds($formazione->id);
    $consulenzaDescendantIds = $hierarchy->descendantIds($consulenza->id);

    // management_mode cascade (was: 'cascades "single" from the Formazione root to every descendant...', 'leaves "Consulenza" and its descendants at "multiple"')
    expect($formazioneDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $formazioneDescendantIds)->where('management_mode', '!=', CategoryManagementMode::Single)->count())
        ->toBe(0)
        ->and($consulenzaDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $consulenzaDescendantIds)->where('management_mode', '!=', CategoryManagementMode::Multiple)->count())
        ->toBe(0);

    // single_quote_per_opportunity cascade (was: 'cascades the one-offer rule...', 'leaves "Consulenza" and its descendants without the one-offer rule')
    expect($formazioneDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $formazioneDescendantIds)->where('single_quote_per_opportunity', false)->count())
        ->toBe(0)
        ->and($consulenzaDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $consulenzaDescendantIds)->where('single_quote_per_opportunity', true)->count())
        ->toBe(0);

    // generates_contract cascade (AC-013, was: 'AC-013: cascades the no-contract rule...', 'AC-013: leaves "Consulenza" and its descendants generating contracts')
    expect($formazioneDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $formazioneDescendantIds)->where('generates_contract', true)->count())
        ->toBe(0)
        ->and($consulenzaDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $consulenzaDescendantIds)->where('generates_contract', false)->count())
        ->toBe(0);

    // simplified_offer_line cascade (AC-007, was: 'AC-007: cascades the simplified offer line...', 'AC-007: leaves "Consulenza" and its descendants without the simplified offer line').
    // "Corsi E-Campus" is the one node of the branch overriding it (spec 0188).
    expect($formazioneDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $formazioneDescendantIds)->where('simplified_offer_line', false)->pluck('name')->all())
        ->toBe([ECampusCourseCatalogue::CATEGORY])
        ->and($consulenzaDescendantIds)->not->toBeEmpty()
        ->and(ProductCategory::query()->whereIn('id', $consulenzaDescendantIds)->where('simplified_offer_line', true)->count())
        ->toBe(0);

    // manager_labels resolution (was: 'resolves the "Formazione" G.A. labels on the whole branch...', 'leaves "Consulenza" without its own G.A. labels')
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    expect(app(CategoryManagerLabelResolver::class)->effectiveManagerLabels($molise))
        ->toBe([1 => 'Tutor', 2 => 'Operatore', 3 => 'Partner commerciale', 4 => 'Segnalatore'])
        ->and($consulenza->manager_labels)->toBeEmpty();

    // Captured right after the first seed, checked once the re-run has
    // happened below (was: 'does not touch already-aligned management_mode rows on re-run').
    $moliseUpdatedAt = $molise->updated_at;

    test()->seed(QualificaCatalogSeeder::class); // re-run: realigned, not duplicated.

    // Idempotency of every root-owned rule (was the five "...idempotently" tests).
    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('management_mode'))
        ->toBe(CategoryManagementMode::Single)
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('management_mode'))
        ->toBe(CategoryManagementMode::Multiple)
        ->and(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('single_quote_per_opportunity'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('single_quote_per_opportunity'))
        ->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('generates_contract'))
        ->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('generates_contract'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('simplified_offer_line'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->value('simplified_offer_line'))
        ->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'APL')->whereNull('parent_id')->value('simplified_offer_line'))
        ->toBeFalsy()
        ->and($formazione->fresh()->manager_labels)
        ->toBe([1 => 'Tutor', 2 => 'Operatore', 3 => 'Partner commerciale', 4 => 'Segnalatore']);

    expect($molise->fresh()->updated_at)->toEqual($moliseUpdatedAt);
});

/**
 * One scenario instead of one test per property: each directive realigns a
 * single, independent column (CatalogRootRules::apply() saves all root
 * columns in one write, then each *Inheritance::syncSubtree() re-syncs only
 * its own column) — so the five pre-existing-installation states merge onto
 * the SAME branch without one clobbering another's check.
 */
it('realigns a branch seeded before every directive existed, cascading each independent property to its descendants', function (): void {
    // The state of an installation seeded before any of the five directives:
    // Formazione carries the pre-directive value on every rule at once, and
    // its GOL / GOL - Molise descendants carry the four that cascade.
    $formazione = ProductCategory::factory()->create([
        'name' => 'Formazione',
        'management_mode' => CategoryManagementMode::Multiple,
        'single_quote_per_opportunity' => false,
        'generates_contract' => true,
        'simplified_offer_line' => false,
        'manager_labels' => null,
    ]);
    $gol = ProductCategory::factory()->create([
        'name' => 'GOL',
        'parent_id' => $formazione->id,
        'management_mode' => CategoryManagementMode::Multiple,
        'single_quote_per_opportunity' => false,
        'generates_contract' => true,
        'simplified_offer_line' => false,
    ]);
    ProductCategory::factory()->create([
        'name' => 'GOL - Molise',
        'parent_id' => $gol->id,
        'management_mode' => CategoryManagementMode::Multiple,
        'single_quote_per_opportunity' => false,
        'generates_contract' => true,
        'simplified_offer_line' => false,
    ]);

    test()->seed(QualificaCatalogSeeder::class);

    // management_mode (was: 'realigns a "Formazione" branch seeded as "multiple" before this directive, cascading to its descendants')
    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('management_mode'))
        ->toBe(CategoryManagementMode::Single)
        ->and(ProductCategory::query()->where('name', 'GOL')->value('management_mode'))->toBe(CategoryManagementMode::Single)
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('management_mode'))->toBe(CategoryManagementMode::Single);

    // single_quote_per_opportunity (was: 'realigns a "Formazione" branch seeded before the one-offer directive, cascading to its descendants')
    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('single_quote_per_opportunity'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('single_quote_per_opportunity'))->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('single_quote_per_opportunity'))->toBeTruthy();

    // generates_contract (AC-014, was: 'AC-014: realigns a "Formazione" branch seeded before the no-contract directive, cascading to its descendants')
    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('generates_contract'))
        ->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('generates_contract'))->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('generates_contract'))->toBeFalsy();

    // simplified_offer_line (AC-007, was: 'AC-007: realigns a "Formazione" branch seeded before the simplified-offer-line directive, cascading to its descendants')
    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('simplified_offer_line'))
        ->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('simplified_offer_line'))->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'GOL - Molise')->value('simplified_offer_line'))->toBeTruthy();

    // manager_labels (was: 'realigns a "Formazione" root seeded before the G.A. label directive')
    expect(ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->value('manager_labels'))
        ->toBe([1 => 'Tutor', 2 => 'Operatore', 3 => 'Partner commerciale', 4 => 'Segnalatore']);
});
