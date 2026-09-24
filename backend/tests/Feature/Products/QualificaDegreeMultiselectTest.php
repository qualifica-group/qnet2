<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaContactProcessingSeeder;
use Database\Seeders\QualificaQuoteLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "Titolo di Studio" is a multiselect that absorbed "Qualifica Professionale"
// as an option, and the separate "Qualifica Professionale" field on
// "Autofinanziato" is retired (user directive 2026-09-24).

uses(RefreshDatabase::class);

const DEGREE_LAYOUT_CONTEXTS = [AttributeContext::Quote, AttributeContext::WorkOrder];

const RETIRED_QUALIFICATION = 'professional_qualification';

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

function degreeAttribute(): Attribute
{
    return Attribute::query()->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)->firstOrFail();
}

it('seeds "Titolo di Studio" as a multiselect with "Qualifica Professionale" among its options', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $degree = degreeAttribute();

    expect($degree->type)->toBe('enum')
        ->and($degree->config)->toBe(['display' => 'multiselect'])
        ->and($degree->options()->orderBy('sort_order')->pluck('label')->last())->toBe('Qualifica Professionale')
        ->and(Attribute::query()->where('code', RETIRED_QUALIFICATION)->exists())->toBeFalse();
});

it('converges an installation seeded with the single pick and the separate field', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Rewind to the previous revision: "Titolo di Studio" a single pick, and
    // "Qualifica Professionale" assigned to "Autofinanziato" with its own row.
    $category = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::SELF_FUNDED_CATEGORY)->firstOrFail();
    $degree = degreeAttribute();
    $degree->update(['config' => null]);
    $qualification = Attribute::query()->create(['code' => RETIRED_QUALIFICATION, 'name' => 'Qualifica Professionale', 'type' => 'enum', 'config' => ['display' => 'multiselect']]);
    $service = app(AttributeLayoutService::class);

    foreach (DEGREE_LAYOUT_CONTEXTS as $context) {
        $category->attributes()->attach($qualification->id, ['context' => $context->value, 'is_required' => false, 'sort_order' => 0]);

        $blob = $service->resolveExact($category, $context, LayoutFormScope::All);
        $index = collect($blob['sections'])->search(static fn (array $section): bool => $section['id'] === 'contact-processing');
        $row = end($blob['sections'][$index]['rows']);
        $row['id'] .= '-qualification';
        $row['items'] = [[...$row['items'][0], 'attribute_code' => RETIRED_QUALIFICATION]];
        $blob['sections'][$index]['rows'][] = $row;
        $service->upsert($category, $context, LayoutFormScope::All, $blob);
    }

    $quote = Quote::factory()->create(['attribute_values' => ['degree' => 'high_school']]);
    $workOrder = WorkOrder::factory()->create(['attribute_values' => ['degree' => 'degree', 'cpi' => 'Milano']]);

    test()->seed(QualificaContactProcessingSeeder::class);
    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect(degreeAttribute()->config)->toBe(['display' => 'multiselect'])
        ->and($qualification->categories()->exists())->toBeFalse()
        // The stored single values now read as one-element lists.
        ->and($quote->fresh()->attribute_values)->toBe(['degree' => ['high_school']])
        ->and($workOrder->fresh()->attribute_values)->toBe(['degree' => ['degree'], 'cpi' => 'Milano']);

    foreach (DEGREE_LAYOUT_CONTEXTS as $context) {
        $rows = selfFundedContactRows($context);

        expect($rows)->not->toContain([RETIRED_QUALIFICATION])
            ->and(end($rows))->toBe(['foreign_user_documents', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE], $context->value);
    }
});

it('leaves a value already stored as a list untouched on re-seed', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    DB::table('attributes')->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)->update(['config' => null]);
    $quote = Quote::factory()->create(['attribute_values' => ['degree' => ['diploma', 'professional_qualification']]]);

    test()->seed(QualificaContactProcessingSeeder::class);

    expect($quote->fresh()->attribute_values)->toBe(['degree' => ['diploma', 'professional_qualification']]);
});
