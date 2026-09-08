<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaQuoteLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The offer form (spec 0062) the client's catalogue seeds on the Formazione
// branch and the two Consulenza leaves: three sections from three catalogues,
// one layout row per category, since a layout is never inherited the way an
// attribute assignment is.
uses(RefreshDatabase::class);

/**
 * The whole Formazione branch: every category the seeder writes a layout on,
 * plus the two Consulenza leaves that carry their own set.
 *
 * @var list<string>
 */
const QUOTE_LAYOUT_CATEGORIES = [
    'Formazione', 'GOL', 'Autoimpiego', 'Yisu', 'Autofinanziato', 'DIL',
    'GOL - Molise', 'GOL - Abruzzo', 'GOL - Calabria', 'GOL - Campania',
    'GOL - Lombardia', 'GOL - Lazio', 'GOL - Umbria', 'GOL - Puglia',
    'GOL - Basilicata', 'GOL - Sicilia',
    'Trattative in Corso', 'Presa Appuntamenti',
];

function quoteLayoutOf(string $categoryName): array
{
    $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

    return app(AttributeLayoutService::class)
        ->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All);
}

/**
 * @return list<string>
 */
function codesOfSection(array $layout, string $sectionId): array
{
    $section = collect($layout['sections'])->firstWhere('id', $sectionId);

    return collect($section['rows'] ?? [])
        ->flatMap(fn (array $row): array => array_column($row['items'], 'attribute_code'))
        ->all();
}

it('seeds one offer layout per contributing category, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaQuoteLayoutSeeder::class); // re-run: the configured layout is left alone.

    foreach (QUOTE_LAYOUT_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        $rows = AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', AttributeContext::Quote->value)
            ->get();

        expect($rows)->toHaveCount(1, $name)
            ->and($rows->first()->form_mode)->toBe(LayoutFormScope::All);
    }

    expect(AttributeLayout::query()->where('context', AttributeContext::Quote->value)->count())
        ->toBe(count(QUOTE_LAYOUT_CATEGORIES));
});

it('moves the course and classroom fields off the product form entirely', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // User directive 2026-09-08: both sets live on the Offerta now. Nothing
    // assigns nor lays them out on the product side any more.
    expect(AttributeLayout::query()->where('context', AttributeContext::Product->value)->count())->toBe(0);

    $hierarchy = app(CategoryHierarchy::class);

    foreach (['Formazione', 'GOL - Molise', 'Autofinanziato'] as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        expect($hierarchy->effectiveAttributes($category, AttributeContext::Product)->all())
            ->toBe([], $name);
    }
});

it('orders the three catalogue sections, each pruned to what the category resolves', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $sectionIds = fn (string $name): array => array_column(quoteLayoutOf($name)['sections'], 'id');

    // A regional leaf: the course duration, the classroom edition, the contact
    // processing set — in reading order.
    expect($sectionIds('GOL - Molise'))->toBe(['course-data', 'classroom-data', 'contact-processing'])
        ->and($sectionIds('Autofinanziato'))->toBe(['course-data', 'classroom-data', 'contact-processing'])
        // A Consulenza leaf carries none of the training catalogues.
        ->and($sectionIds('Trattative in Corso'))->toBe(['contact-processing']);

    // "Modalità di svolgimento" is confined to its own subtree, the duration
    // reaches the whole branch.
    expect(codesOfSection(quoteLayoutOf('GOL - Molise'), 'course-data'))->toBe(['total_hours'])
        ->and(codesOfSection(quoteLayoutOf('Autofinanziato'), 'course-data'))->toBe(['total_hours', 'delivery_mode'])
        ->and(codesOfSection(quoteLayoutOf('GOL - Molise'), 'classroom-data'))
        ->toBe(ClassroomAttributeCatalogue::codes());

    // Sort order follows the array order, with no gap left by a dropped section.
    expect(array_column(quoteLayoutOf('GOL - Molise')['sections'], 'sort_order'))->toBe([0, 1, 2])
        ->and(array_column(quoteLayoutOf('Trattative in Corso')['sections'], 'sort_order'))->toBe([0]);
});

it('places every attribute the category resolves, leaving none to the synthesized section', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $hierarchy = app(CategoryHierarchy::class);

    foreach (QUOTE_LAYOUT_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        $effective = $hierarchy->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all();

        $placed = collect(quoteLayoutOf($name)['sections'])
            ->flatMap(fn (array $section): array => collect($section['rows'])
                ->flatMap(fn (array $row): array => array_column($row['items'], 'attribute_code'))
                ->all())
            ->sort()
            ->values()
            ->all();

        // An unplaced attribute would be demoted to the renderer's synthesized,
        // collapsed "other information" section: this is the guard that a
        // catalogue cannot grow a field without a section to hold it.
        expect($placed)->toBe($effective, $name);
    }
});

it('pairs the classroom fields two per row in a two-column section', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $section = collect(quoteLayoutOf('GOL - Molise')['sections'])->firstWhere('id', 'classroom-data');

    expect($section['title'])->toBe('Dati Aula')
        ->and($section['columns'])->toBe(2)
        ->and($section['rows'])->toHaveCount(count(ClassroomAttributeCatalogue::ROWS))
        ->and(array_column($section['rows'][0]['items'], 'attribute_code'))->toBe(['teacher', 'classroom_status'])
        ->and(array_column($section['rows'][0]['items'], 'width'))->toBe(['half', 'half']);

    $courseSection = collect(quoteLayoutOf('Autofinanziato')['sections'])->firstWhere('id', 'course-data');

    expect($courseSection['title'])->toBe('Dati corso')
        ->and(array_column($courseSection['rows'][0]['items'], 'attribute_code'))->toBe(['total_hours', 'delivery_mode']);
});

it('renders in every form mode through the shared scope', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    foreach (FormMode::cases() as $mode) {
        expect($service->resolveWithFallback($molise, AttributeContext::Quote, $mode))->not->toBeNull($mode->value);
    }
});

it('never overwrites a layout configured by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    $configured = $service->upsert($molise, AttributeContext::Quote, LayoutFormScope::All, [
        'sections' => [[
            'id' => 'by-hand',
            'title' => 'Configurata a mano',
            'description' => null,
            'variant' => 'highlighted',
            'collapsible' => true,
            'default_collapsed' => true,
            'columns' => 1,
            'sort_order' => 0,
            'rows' => [['id' => 'by-hand-0', 'items' => [['attribute_code' => 'exam_date', 'width' => 'full']]]],
        ]],
    ]);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect($service->resolveExact($molise, AttributeContext::Quote, LayoutFormScope::All))->toBe($configured);
});

it('recomposes the single-section layout the previous revision seeded', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    // The blob the previous revision wrote, when QualificaContactProcessingSeeder
    // still owned this context: its section alone, in first position.
    $legacy = quoteLayoutOf('GOL - Molise');
    $legacy['sections'] = [array_merge(
        collect($legacy['sections'])->firstWhere('id', 'contact-processing'),
        ['sort_order' => 0],
    )];
    $service->upsert($molise, AttributeContext::Quote, LayoutFormScope::All, $legacy);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    // Recognised and recomposed: without this an installation already seeded
    // would never show the two training sections.
    expect(array_column(quoteLayoutOf('GOL - Molise')['sections'], 'id'))
        ->toBe(['course-data', 'classroom-data', 'contact-processing']);
});

it('leaves a category outside the two branches flat', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    foreach (['Consulenza', 'APL', 'Orientamento Specialistico'] as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        expect(app(AttributeLayoutService::class)->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All))
            ->toBeNull($name);
    }
});
