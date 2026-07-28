<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaClassroomLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Dati Aula" form section (spec 0062) the client's catalogue seeds on the
// whole Formazione branch — one row per category, since a layout is never
// inherited the way an attribute assignment is.
uses(RefreshDatabase::class);

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

it('seeds the "Dati Aula" section on every category of the Formazione branch, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaClassroomLayoutSeeder::class); // re-run: the configured layout is left alone.

    $branch = [
        'Formazione', 'GOL', 'Autoimpiego', 'Yisu', 'Autofinanziato', 'DIL',
        'GOL - Molise', 'GOL - Abruzzo', 'GOL - Calabria', 'GOL - Campania',
        'GOL - Lombardia', 'GOL - Lazio', 'GOL - Umbria', 'GOL - Puglia',
        'GOL - Basilicata', 'GOL - Sicilia',
    ];

    foreach ($branch as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        $rows = AttributeLayout::query()
            ->where('product_category_id', $category->id)
            ->where('context', AttributeContext::Product->value)
            ->get();

        expect($rows)->toHaveCount(1, $name)
            ->and($rows->first()->form_mode)->toBe(LayoutFormScope::All)
            ->and(codesOfSection($rows->first()->layout, 'classroom-data'))
            ->toBe(ClassroomAttributeCatalogue::codes(), $name);
    }

    // The branch and nothing else: the other root has no classroom attribute.
    // Scoped to this context — the Opportunity-context sections
    // (QualificaContactProcessingSeeder) are their own rows on the same
    // categories.
    expect(AttributeLayout::query()->where('context', AttributeContext::Product->value)->count())
        ->toBe(count($branch));
});

it('keeps the branch attributes visible in a leading "Dati corso" section', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();

    $moliseLayout = $service->resolveExact($molise, AttributeContext::Product, LayoutFormScope::All);
    $autofinanziatoLayout = $service->resolveExact($autofinanziato, AttributeContext::Product, LayoutFormScope::All);

    // An unplaced attribute would be demoted to the renderer's synthesized,
    // collapsed "other information" section: both non-classroom attributes are
    // placed, the subcategory's own one included.
    expect(codesOfSection($moliseLayout, 'course-data'))->toBe(['total_hours'])
        ->and(codesOfSection($autofinanziatoLayout, 'course-data'))->toBe(['total_hours', 'delivery_mode'])
        ->and($moliseLayout['sections'][0]['sort_order'])->toBe(0)
        ->and($moliseLayout['sections'][1]['sort_order'])->toBe(1);
});

it('pairs the classroom fields two per row in a two-column section', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    $layout = app(AttributeLayoutService::class)
        ->resolveExact($molise, AttributeContext::Product, LayoutFormScope::All);

    $section = collect($layout['sections'])->firstWhere('id', 'classroom-data');

    expect($section['title'])->toBe('Dati Aula')
        ->and($section['columns'])->toBe(2)
        ->and($section['rows'])->toHaveCount(count(ClassroomAttributeCatalogue::ROWS))
        ->and(array_column($section['rows'][0]['items'], 'attribute_code'))->toBe(['teacher', 'classroom_status'])
        ->and(array_column($section['rows'][0]['items'], 'width'))->toBe(['half', 'half']);
});

it('renders in every form mode through the shared scope', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    foreach (FormMode::cases() as $mode) {
        expect($service->resolveWithFallback($molise, AttributeContext::Product, $mode))->not->toBeNull($mode->value);
    }
});

it('never overwrites a layout configured by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    $configured = $service->upsert($molise, AttributeContext::Product, LayoutFormScope::All, [
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

    test()->seed(QualificaClassroomLayoutSeeder::class);

    expect($service->resolveExact($molise, AttributeContext::Product, LayoutFormScope::All))->toBe($configured);
});

it('leaves the Consulenza root flat', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();

    expect(app(AttributeLayoutService::class)->resolveExact($consulenza, AttributeContext::Product, LayoutFormScope::All))
        ->toBeNull();
});
