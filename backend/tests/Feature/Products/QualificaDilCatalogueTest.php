<?php

use App\DataObjects\QuoteWorkflows\UpdateQuoteWorkflowData;
use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalog\DilCourseCatalogue;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The DIL course catalogue (user directive 2026-09-17): "DIL" becomes a
// container like GOL, its courses filed on the "DIL - Lombardia" leaf, which
// inherits DIL's own offer fields, form and working states.
uses(RefreshDatabase::class);

const DIL_LOMBARDY = 'DIL - Lombardia';

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

function dilCategory(string $name): ProductCategory
{
    return ProductCategory::query()->where('name', $name)->firstOrFail();
}

/**
 * @param  array<int, array{field: string, value_id: int}>  $criteria
 */
function setDilWorkflowCriteria(array $criteria): void
{
    $workflow = QuoteWorkflow::query()->where('name', 'DIL')->firstOrFail();

    app(QuoteWorkflowService::class)->update($workflow, new UpdateQuoteWorkflowData(criteria: $criteria));
}

it('seeds "DIL - Lombardia" as the selectable leaf of a "DIL" container, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural keys, no duplicates.

    $dil = dilCategory('DIL');
    $lombardy = dilCategory(DIL_LOMBARDY);

    expect($dil->is_selectable)->toBeFalsy()
        ->and($lombardy->parent_id)->toBe($dil->id)
        ->and($lombardy->is_selectable)->toBeTruthy()
        ->and(ProductCategory::query()->where('name', DIL_LOMBARDY)->count())->toBe(1)
        // A container hosts no product: the single "DIL" offer is no longer seeded.
        ->and(Product::query()->where('category_id', $dil->id)->exists())->toBeFalse();
});

it('files every DIL course on "DIL - Lombardia", the repeated name split by duration', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural key (name, category), no duplicates.

    $names = Product::query()->where('category_id', dilCategory(DIL_LOMBARDY)->id)->pluck('name');

    expect($names)->toHaveCount(count(DilCourseCatalogue::COURSES[DIL_LOMBARDY]))
        ->and($names->unique())->toHaveCount($names->count())
        ->and($names->all())->toContain(
            'Google Workspace',
            'Consulenza olistica di base',
            'Make-up Artist Professionale (30 ore)',
            'Make-up Artist Professionale (40 ore)',
        )
        ->and($names->all())->not->toContain('Make-up Artist Professionale');
});

it('gives "DIL - Lombardia" exactly the offer fields and form of "DIL"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $hierarchy = app(CategoryHierarchy::class);
    $layouts = app(AttributeLayoutService::class);
    $dil = dilCategory('DIL');
    $lombardy = dilCategory(DIL_LOMBARDY);

    foreach ([AttributeContext::Quote, AttributeContext::WorkOrder] as $context) {
        expect($hierarchy->effectiveAttributes($lombardy, $context)->pluck('code')->all())
            ->toBe($hierarchy->effectiveAttributes($dil, $context)->pluck('code')->all(), $context->value)
            ->and($layouts->resolveWithFallback($lombardy, $context, FormMode::Create))
            ->toBe($layouts->resolveWithFallback($dil, $context, FormMode::Create), $context->value);
    }

    // DIL's barrier still holds for its child: no Formazione-only field.
    expect($hierarchy->effectiveAttributes($lombardy, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('delivery_mode');
});

it('converges an installation seeded while "DIL" hosted its own offer', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The state the previous revision left: DIL selectable, its own product,
    // its workflow matched on the exact category.
    $dil = dilCategory('DIL');
    $dil->update(['is_selectable' => true]);
    $legacyProduct = Product::factory()->create(['name' => 'DIL', 'category_id' => $dil->id]);
    setDilWorkflowCriteria([['field' => WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD, 'value_id' => $dil->id]]);

    test()->seed(QualificaCatalogSeeder::class);

    $workflow = QuoteWorkflow::query()->where('name', 'DIL')->with('criteria')->firstOrFail();

    expect($dil->fresh()->is_selectable)->toBeFalsy()
        // Never deleted: offers may already reference it.
        ->and($legacyProduct->fresh())->not->toBeNull()
        ->and($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe(WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD)
        ->and($workflow->criteria->first()->value_id)->toBe($dil->id)
        ->and(QuoteWorkflow::query()->where('name', 'DIL')->count())->toBe(1);
});

it('leaves a DIL workflow re-pointed from the configurator alone', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $lombardy = dilCategory(DIL_LOMBARDY);
    setDilWorkflowCriteria([['field' => WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD, 'value_id' => $lombardy->id]]);

    test()->seed(QualificaCatalogSeeder::class);

    $criterion = QuoteWorkflow::query()->where('name', 'DIL')->with('criteria')->firstOrFail()->criteria->sole();

    expect($criterion->field)->toBe(WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD)
        ->and($criterion->value_id)->toBe($lombardy->id);
});
