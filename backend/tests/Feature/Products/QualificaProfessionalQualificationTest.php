<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaContactProcessingSeeder;
use Database\Seeders\QualificaQuoteLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "Autofinanziato" clones "Titolo di Studio" as the multiselect "Qualifica
// Professionale" (user directive 2026-09-23), and an installation seeded
// before it converges on re-seed.

uses(RefreshDatabase::class);

const QUALIFICATION_LAYOUT_CONTEXTS = [AttributeContext::Quote, AttributeContext::WorkOrder];

/**
 * @return list<list<string>>
 */
function selfFundedContactRows(AttributeContext $context): array
{
    $category = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::SELF_FUNDED_CATEGORY)->firstOrFail();
    $blob = app(AttributeLayoutService::class)->resolveWithFallback($category, $context, FormMode::Create);
    $section = collect($blob['sections'])->firstWhere('id', 'contact-processing');

    return array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $section['rows']);
}

/**
 * @return list<string>
 */
function categoryCodes(string $name, AttributeContext $context): array
{
    $category = ProductCategory::query()->where('name', $name)->firstOrFail();

    return $category->attributes()->wherePivot('context', $context->value)->pluck('code')->all();
}

it('clones the degree pick list as a multiselect on "Autofinanziato" alone', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $qualification = Attribute::query()
        ->where('code', ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION)
        ->firstOrFail();
    $degree = Attribute::query()->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)->firstOrFail();
    $optionsOf = static fn (Attribute $attribute): array => $attribute->options()->orderBy('sort_order')->get(['value', 'label'])->toArray();

    expect($qualification->name)->toBe('Qualifica Professionale')
        ->and($qualification->type)->toBe('enum')
        ->and($qualification->config)->toBe(['display' => 'multiselect'])
        ->and($optionsOf($qualification))->toBe($optionsOf($degree))
        // The source field is untouched: still a single pick.
        ->and($degree->config)->toBeNull();

    foreach (QUALIFICATION_LAYOUT_CONTEXTS as $context) {
        expect(categoryCodes(ContactProcessingAttributeCatalogue::SELF_FUNDED_CATEGORY, $context))
            ->toContain(ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION)
            ->and(categoryCodes(ContactProcessingAttributeCatalogue::TRAINING_CATEGORY, $context))
            ->not->toContain(ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION)
            ->and(selfFundedContactRows($context))
            ->toContain([ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION]);
    }
});

it('converges an installation seeded before "Qualifica Professionale"', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Rewind "Autofinanziato": the field unassigned and each layout stripped of
    // its row, i.e. exactly what the previous revision composed.
    $category = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::SELF_FUNDED_CATEGORY)->firstOrFail();
    $qualification = Attribute::query()->where('code', ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION)->firstOrFail();
    $service = app(AttributeLayoutService::class);

    DB::table('attribute_category')->where('category_id', $category->id)->where('attribute_id', $qualification->id)->delete();

    foreach (QUALIFICATION_LAYOUT_CONTEXTS as $context) {
        $blob = $service->resolveExact($category, $context, LayoutFormScope::All);
        $blob['sections'] = array_map(static function (array $section): array {
            $section['rows'] = array_values(array_filter(
                $section['rows'],
                static fn (array $row): bool => array_column($row['items'], 'attribute_code') !== [ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION],
            ));

            return $section;
        }, $blob['sections']);
        $service->upsert($category, $context, LayoutFormScope::All, $blob);

        expect(selfFundedContactRows($context))->not->toContain([ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION]);
    }

    test()->seed(QualificaContactProcessingSeeder::class);
    test()->seed(QualificaQuoteLayoutSeeder::class);

    // Both layouts recognised as seeded and recomposed: the field sits in the
    // section, right under "Titolo di Studio", not in "Altre informazioni".
    foreach (QUALIFICATION_LAYOUT_CONTEXTS as $context) {
        $rows = selfFundedContactRows($context);

        expect(array_slice($rows, -2))->toBe([
            ['foreign_user_documents', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE],
            [ContactProcessingAttributeCatalogue::PROFESSIONAL_QUALIFICATION],
        ], $context->value);
    }
});
