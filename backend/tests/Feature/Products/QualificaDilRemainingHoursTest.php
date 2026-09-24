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

// "DIL" gets a numeric "Residuo Ore" of its own (user directive 2026-09-24),
// and an installation seeded before it converges on re-seed.

uses(RefreshDatabase::class);

const REMAINING_HOURS_CONTEXTS = [AttributeContext::Quote, AttributeContext::WorkOrder];

function remainingHoursDil(): ProductCategory
{
    return ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
}

/**
 * @return list<list<string>>
 */
function remainingHoursContactRows(AttributeContext $context): array
{
    $blob = app(AttributeLayoutService::class)->resolveWithFallback(remainingHoursDil(), $context, FormMode::Create);
    $section = collect($blob['sections'])->firstWhere('id', 'contact-processing');

    return array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $section['rows']);
}

it('seeds "Residuo Ore" as an integer field of "DIL" alone, in both contexts', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate attribute nor pivot row.

    $attribute = Attribute::query()->where('code', ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS)->sole();

    expect($attribute->name)->toBe('Residuo Ore')
        ->and($attribute->type)->toBe('integer');

    $assignments = DB::table('attribute_category')->where('attribute_id', $attribute->id)->get();

    expect($assignments->pluck('category_id')->unique()->all())->toBe([remainingHoursDil()->id])
        ->and($assignments->pluck('context')->sort()->values()->all())
        ->toBe([AttributeContext::Quote->value, AttributeContext::WorkOrder->value]);

    // The rest of the Formazione branch keeps its own "Residuo Ore Dote" only.
    expect(app(CategoryHierarchy::class)
        ->effectiveAttributes(ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail(), AttributeContext::Quote)
        ->pluck('code')
        ->all())
        ->toContain('dote_remaining_hours')
        ->not->toContain(ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS);
});

it('places "Residuo Ore" on its own row, right after the DOTE dates', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    foreach (REMAINING_HOURS_CONTEXTS as $context) {
        $rows = remainingHoursContactRows($context);
        $datesIndex = array_search(['dote_activation_date', 'dote_expiry_date'], $rows, true);

        expect($datesIndex)->not->toBeFalse($context->value)
            ->and($rows[$datesIndex + 1])->toBe([ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS], $context->value);
    }
});

it('converges an installation seeded before "Residuo Ore" joined "DIL"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Rewind to the previous revision: the field absent, and each layout
    // exactly as that revision composed it — today's, minus the new row.
    $service = app(AttributeLayoutService::class);
    $dil = remainingHoursDil();

    foreach (REMAINING_HOURS_CONTEXTS as $context) {
        $blob = $service->resolveWithFallback($dil, $context, FormMode::Create);
        $blob['sections'] = array_map(static function (array $section): array {
            $section['rows'] = array_values(array_filter(
                $section['rows'],
                static fn (array $row): bool => array_column($row['items'], 'attribute_code') !== [ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS],
            ));

            return $section;
        }, $blob['sections']);
        $service->upsert($dil, $context, LayoutFormScope::All, $blob);
    }

    Attribute::query()->where('code', ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS)->delete();

    test()->seed(QualificaContactProcessingSeeder::class);
    test()->seed(QualificaQuoteLayoutSeeder::class);

    // Recognised as seeded and recomposed: the field sits in the section,
    // not stranded in "Altre informazioni".
    foreach (REMAINING_HOURS_CONTEXTS as $context) {
        expect(remainingHoursContactRows($context))
            ->toContain([ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS]);
    }
});
