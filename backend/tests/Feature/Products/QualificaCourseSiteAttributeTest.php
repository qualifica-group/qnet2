<?php

use App\CustomFields\CustomFieldEntityRegistry;
use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaContactProcessingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "Sede corso" (user directive 2026-09-10): a relation to an operational site,
// sharing the row of "ID Corso". Split out of QualificaContactProcessingSeeder
// Test, which had reached the 500-line hard limit (engineering.md §6).

uses(RefreshDatabase::class);

// Guarded redefinitions: whichever of the two files Pest loads first defines
// them, the convention this test directory already follows.
if (! function_exists('contactSectionOf')) {
    function contactSectionOf(array $blob): array
    {
        return collect($blob['sections'])->firstWhere('id', 'contact-processing');
    }
}

if (! function_exists('effectiveCodes')) {
    /**
     * @return list<string>
     */
    function effectiveCodes(string $categoryName, AttributeContext $context): array
    {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        return app(CategoryHierarchy::class)
            ->effectiveAttributes($category, $context)
            ->pluck('code')
            ->all();
    }
}

it('seeds "Sede corso" as a relation to an operational site, on the Formazione root', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate attribute nor pivot row.

    $courseSite = Attribute::query()->where('code', ContactProcessingAttributeCatalogue::COURSE_SITE)->firstOrFail();

    expect(Attribute::query()->where('code', ContactProcessingAttributeCatalogue::COURSE_SITE)->count())->toBe(1)
        ->and($courseSite->name)->toBe('Sede corso')
        ->and($courseSite->type)->toBe('relation')
        ->and($courseSite->relation_target)->toBe([
            'entity_type' => 'operational-sites',
            'cardinality' => 'one',
            'for_select_resource' => 'operational-sites',
        ]);

    // Assigned on the root, so the whole branch resolves it by inheritance —
    // and in BOTH contexts, like the rest of this catalogue.
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $assignments = DB::table('attribute_category')->where('attribute_id', $courseSite->id)->get();

    expect($assignments->pluck('category_id')->unique()->values()->all())->toBe([$formazione->id])
        ->and($assignments->pluck('context')->sort()->values()->all())
        ->toBe([AttributeContext::Quote->value, AttributeContext::WorkOrder->value]);

    expect(effectiveCodes('GOL - Molise', AttributeContext::Quote))
        ->toContain(ContactProcessingAttributeCatalogue::COURSE_SITE);
});

it('its relation target is a custom-fieldable domain, which is what makes the relation resolvable', function (): void {
    // The condition CustomFieldEntityRegistry imposes on every `entity_type`:
    // registered in BOTH config/tables.php and config/authorization.php. A
    // target failing it would render an empty select at runtime, silently.
    expect(app(CustomFieldEntityRegistry::class)->isCustomFieldable('operational-sites'))->toBeTrue();
});

it('places "Sede corso" right after "ID Corso", sharing its row', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    foreach ([AttributeContext::Quote, AttributeContext::WorkOrder] as $context) {
        $section = contactSectionOf($service->resolveWithFallback($molise, $context, FormMode::Create));
        $rows = array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $section['rows'],
        );

        expect(in_array(['id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE], $rows, true))
            ->toBeTrue($context->value);
    }
});

it('recomposes the layout of an installation seeded before "Sede corso" existed', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    // Rewind the Commessa layout to the composition the previous revision
    // wrote: today's rows minus the new field.
    $effective = effectiveCodes('Formazione', AttributeContext::WorkOrder);
    $previous = array_values(array_filter(
        array_map(
            static fn (array $row): array => array_values(array_intersect($row, $effective)),
            ContactProcessingAttributeCatalogue::PREVIOUS_ROWS,
        ),
        static fn (array $row): bool => $row !== [],
    ));
    $blob = $service->resolveWithFallback($formazione, AttributeContext::WorkOrder, FormMode::Create);
    $blob['sections'][0]['rows'] = array_map(
        static fn (array $codes, int $index): array => [
            'id' => sprintf('contact-processing-%d', $index),
            'items' => array_map(
                static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'],
                $codes,
            ),
        ],
        $previous,
        array_keys($previous),
    );
    $service->upsert($formazione, AttributeContext::WorkOrder, LayoutFormScope::All, $blob);

    test()->seed(QualificaContactProcessingSeeder::class);

    // Recognised as its own and recomposed — the new field lands in its
    // section instead of being stranded in the renderer's "Altre informazioni".
    $section = contactSectionOf($service->resolveWithFallback($formazione, AttributeContext::WorkOrder, FormMode::Create));
    $rows = array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $section['rows']);

    expect($rows)->toContain(['id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE]);
});
