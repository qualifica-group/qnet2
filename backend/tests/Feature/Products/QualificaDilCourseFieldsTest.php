<?php

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
use Database\Seeders\QualificaQuoteLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "DIL" takes the GOL course fields — "ID Corso" and "Sede corso" — in place of
// the free-text "Corso Scelto" (user directive 2026-09-16), and an installation
// seeded by the previous revision converges on re-seed.

uses(RefreshDatabase::class);

const DIL_LAYOUT_CONTEXTS = [AttributeContext::Quote, AttributeContext::WorkOrder];

/**
 * @return list<list<string>>
 */
function dilContactRows(AttributeContext $context): array
{
    $category = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $blob = app(AttributeLayoutService::class)->resolveWithFallback($category, $context, FormMode::Create);
    $section = collect($blob['sections'])->firstWhere('id', 'contact-processing');

    return array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $section['rows']);
}

/**
 * @return list<string>
 */
function dilEffectiveCodes(AttributeContext $context): array
{
    $category = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();

    return app(CategoryHierarchy::class)->effectiveAttributes($category, $context)->pluck('code')->all();
}

/**
 * Rewinds "DIL" to what the previous revision seeded: "Corso Scelto" assigned
 * in place of the two course fields, "Residuo Ore" not yet there (it joined on
 * 2026-09-24), and each layout composed from the rows as they stood then —
 * "Corso Scelto" leading, ahead of today's rows.
 */
function rewindDilToChosenCourse(): void
{
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $chosenCourse = Attribute::query()->create(['code' => 'chosen_course', 'name' => 'Corso Scelto', 'type' => 'text']);
    $laterFieldIds = Attribute::query()
        ->whereIn('code', ['id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE, ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS])
        ->pluck('id');
    $service = app(AttributeLayoutService::class);

    foreach (DIL_LAYOUT_CONTEXTS as $context) {
        DB::table('attribute_category')
            ->where('category_id', $dil->id)
            ->where('context', $context->value)
            ->whereIn('attribute_id', $laterFieldIds)
            ->delete();
        $dil->attributes()->attach($chosenCourse->id, ['context' => $context->value, 'is_required' => false, 'sort_order' => 0]);

        $effective = dilEffectiveCodes($context);
        $rows = array_values(array_filter(
            array_map(
                static fn (array $row): array => array_values(array_intersect($row, $effective)),
                [['chosen_course'], ...ContactProcessingAttributeCatalogue::ROWS],
            ),
            static fn (array $row): bool => $row !== [],
        ));

        $blob = $service->resolveWithFallback($dil, $context, FormMode::Create);
        $blob['sections'] = [[
            ...collect($blob['sections'])->firstWhere('id', 'contact-processing'),
            'sort_order' => 0,
            'rows' => array_map(
                static fn (array $codes, int $index): array => [
                    'id' => sprintf('contact-processing-%d', $index),
                    'items' => array_map(static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'], $codes),
                ],
                $rows,
                array_keys($rows),
            ),
        ]];
        $service->upsert($dil, $context, LayoutFormScope::All, $blob);
    }
}

it('assigns "ID Corso" and "Sede corso" to "DIL" in both contexts, dropping "Corso Scelto"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    foreach (DIL_LAYOUT_CONTEXTS as $context) {
        expect(dilEffectiveCodes($context))
            ->toContain('id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE)
            ->not->toContain('chosen_course');

        expect(dilContactRows($context))->toContain(['id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE]);
    }

    // Reused by code: one attribute row each, shared with the GOL branch.
    expect(Attribute::query()->where('code', 'id_corso')->count())->toBe(1)
        ->and(Attribute::query()->where('code', ContactProcessingAttributeCatalogue::COURSE_SITE)->count())->toBe(1)
        ->and(Attribute::query()->where('code', 'chosen_course')->exists())->toBeFalse();
});

it('converges an installation seeded while "DIL" still carried "Corso Scelto"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    rewindDilToChosenCourse();

    test()->seed(QualificaContactProcessingSeeder::class);
    test()->seed(QualificaQuoteLayoutSeeder::class);

    // The row survives, its assignments do not.
    $chosenCourse = Attribute::query()->where('code', 'chosen_course')->firstOrFail();
    expect(DB::table('attribute_category')->where('attribute_id', $chosenCourse->id)->count())->toBe(0);

    // Both layouts recognised as seeded and recomposed: every resolved field
    // placed in the section, none stranded in "Altre informazioni".
    foreach (DIL_LAYOUT_CONTEXTS as $context) {
        $placed = array_merge(...dilContactRows($context));
        $effective = dilEffectiveCodes($context);
        sort($placed);
        sort($effective);

        expect($placed)->toBe($effective, $context->value)
            ->and(dilContactRows($context))->toContain(['id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE]);
    }

    expect(array_merge(...dilContactRows(AttributeContext::Quote)))->toBe([
        'data_scelta_cpi', 'data_app_apl',
        'dote_activation_date', 'dote_expiry_date',
        ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS,
        'subsidy_type',
        'id_corso', ContactProcessingAttributeCatalogue::COURSE_SITE,
    ]);
});

it('leaves a hand-edited "DIL" layout alone, bar the retired field', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    rewindDilToChosenCourse();

    // One row moved: a human's work, not a composition any revision wrote.
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $service = app(AttributeLayoutService::class);
    $blob = $service->resolveWithFallback($dil, AttributeContext::Quote, FormMode::Create);
    $blob['sections'][0]['rows'] = array_reverse($blob['sections'][0]['rows']);
    $service->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, $blob);

    test()->seed(QualificaContactProcessingSeeder::class);
    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect(dilContactRows(AttributeContext::Quote))->toBe([
        ['subsidy_type'],
        ['dote_activation_date', 'dote_expiry_date'],
        ['data_scelta_cpi', 'data_app_apl'],
    ]);
});
