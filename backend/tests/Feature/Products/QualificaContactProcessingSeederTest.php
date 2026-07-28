<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaContactProcessingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// The client's "Dati Lavorazione Contatto" set (OPPORTUNITY context): scoped
// per category, adopting the q-crm rows where the import already created them.
uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function effectiveOpportunityCodes(string $categoryName): array
{
    $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

    return app(CategoryHierarchy::class)
        ->effectiveAttributes($category, AttributeContext::Opportunity)
        ->pluck('code')
        ->all();
}

it('assigns the training set to the Formazione root, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate attribute nor pivot row.

    $specs = ContactProcessingAttributeCatalogue::ATTRIBUTES[ContactProcessingAttributeCatalogue::TRAINING_CATEGORY];
    $codes = array_column($specs, 'code');

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $attributeIds = Attribute::query()->whereIn('code', $codes)->pluck('id');

    expect($attributeIds)->toHaveCount(count($codes));

    $pivot = DB::table('attribute_category')
        ->whereIn('attribute_id', $attributeIds)
        ->where('context', AttributeContext::Opportunity->value)
        ->get();

    expect($pivot)->toHaveCount(count($codes))
        ->and($pivot->pluck('category_id')->unique()->all())->toBe([$formazione->id]);

    // Inherited down the branch, in the OPPORTUNITY context only.
    expect(effectiveOpportunityCodes('GOL - Molise'))->toContain(...$codes)
        ->and(effectiveOpportunityCodes('Trattative in Corso'))->not->toContain('cpi');
});

it('keeps the self-funded and consulting sets on their own categories', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Autofinanziato resolves the branch set AND its own two fields.
    expect(effectiveOpportunityCodes('Autofinanziato'))
        ->toContain('cpi')
        ->toContain('course_time_preference')
        ->toContain('price');

    // Its sibling sees neither of the two.
    expect(effectiveOpportunityCodes('DIL'))
        ->not->toContain('course_time_preference')
        ->not->toContain('price');

    // The company-appointment set sits on BOTH Consulenza leaves, and nowhere else.
    foreach (ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES as $name) {
        expect(effectiveOpportunityCodes($name))->toContain(
            'appointment_date', 'acceptance_date', 'company_name',
            'site_address', 'city', 'requested_service', 'company_referent',
        );
    }

    expect(effectiveOpportunityCodes('Consulenza'))->not->toContain('appointment_date');
});

it('adopts the q-crm row instead of minting a parallel one', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The reused codes stay ONE attribute each, with the label the legacy
    // system gave them (user decision: keep code and label).
    foreach (['cpi' => 'CPI', 'data_app_apl' => 'OK app. APL', 'corso' => 'Corso scelto'] as $code => $name) {
        $rows = Attribute::query()->where('code', $code)->get();

        expect($rows)->toHaveCount(1, $code)
            ->and($rows->first()->name)->toBe($name);
    }

    // A pre-existing row is never relabelled by the seeder.
    Attribute::query()->where('code', 'cpi')->update(['name' => 'Rinominato a mano']);
    test()->seed(QualificaContactProcessingSeeder::class);

    expect(Attribute::query()->where('code', 'cpi')->value('name'))->toBe('Rinominato a mano');
});

it('promotes the imported "Titolo di Studio" to a pick list while it carries no value', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $degree = Attribute::query()->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)->firstOrFail();

    expect($degree->type)->toBe('enum')
        ->and($degree->options()->pluck('label')->all())->toBe([
            'Assolvimento obbligo scolastico', 'Licenza Elementare',
            'Licenza Media', 'Diploma', 'Laurea',
        ]);
});

it('leaves the imported "Titolo di Studio" as text when a request already uses it', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Back to the imported shape, with a request carrying a value.
    Attribute::query()
        ->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)
        ->update(['type' => 'text']);
    Opportunity::factory()->create(['attribute_values' => ['degree' => 'Laurea']]);

    test()->seed(QualificaContactProcessingSeeder::class);

    expect(Attribute::query()->where('code', 'degree')->value('type'))->toBe('text');
});

it('seeds one "Dati Lavorazione Contatto" section per contributing category', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: the configured layout is left alone.

    $service = app(AttributeLayoutService::class);

    // The Formazione branch (16) plus the two Consulenza leaves.
    expect(AttributeLayout::query()->where('context', AttributeContext::Opportunity->value)->count())->toBe(18);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $layout = $service->resolveExact($molise, AttributeContext::Opportunity, LayoutFormScope::All);
    $section = $layout['sections'][0];

    expect($section['title'])->toBe('Dati Lavorazione Contatto')
        ->and($section['columns'])->toBe(2)
        ->and(array_column($section['rows'][0]['items'], 'attribute_code'))
        ->toBe(['data_scelta_cpi', 'data_app_apl']);

    // Each category places exactly what it resolves: the consulting leaf has
    // none of the training rows, Autofinanziato adds its own pair.
    $trattative = ProductCategory::query()->where('name', 'Trattative in Corso')->firstOrFail();
    $consulting = $service->resolveExact($trattative, AttributeContext::Opportunity, LayoutFormScope::All);

    $placed = fn (array $blob): array => collect($blob['sections'][0]['rows'])
        ->flatMap(fn (array $row): array => array_column($row['items'], 'attribute_code'))
        ->all();

    expect($placed($consulting))->toBe([
        'appointment_date', 'acceptance_date', 'company_name',
        'company_referent', 'site_address', 'city', 'requested_service',
    ]);

    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();
    expect($placed($service->resolveExact($autofinanziato, AttributeContext::Opportunity, LayoutFormScope::All)))
        ->toContain('course_time_preference', 'price', 'cpi');
});

it('never overwrites an opportunity layout configured by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    $configured = $service->upsert($molise, AttributeContext::Opportunity, LayoutFormScope::All, [
        'sections' => [[
            'id' => 'by-hand',
            'title' => 'Configurata a mano',
            'description' => null,
            'variant' => 'highlighted',
            'collapsible' => true,
            'default_collapsed' => true,
            'columns' => 1,
            'sort_order' => 0,
            'rows' => [['id' => 'by-hand-0', 'items' => [['attribute_code' => 'cpi', 'width' => 'full']]]],
        ]],
    ]);

    test()->seed(QualificaContactProcessingSeeder::class);

    expect($service->resolveExact($molise, AttributeContext::Opportunity, LayoutFormScope::All))->toBe($configured);
});
