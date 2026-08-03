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

it('hands the CPI appointment time to every GOL region, the APL one to three', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaContactProcessingSeeder::class); // re-run: no duplicate attribute nor pivot row.

    // One assignment each, on the container the whole branch inherits from.
    $gol = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::GOL_CATEGORY)->firstOrFail();
    $cpiTime = Attribute::query()->where('code', 'ora_app_cpi')->firstOrFail();

    expect(Attribute::query()->where('code', 'ora_app_cpi')->count())->toBe(1)
        ->and($cpiTime->type)->toBe('text')
        ->and($cpiTime->categories()->pluck('product_categories.id')->all())->toBe([$gol->id]);

    $regions = ProductCategory::query()->where('parent_id', $gol->id)->pluck('name');
    expect($regions)->toHaveCount(10);

    foreach ($regions as $region) {
        expect(effectiveOpportunityCodes($region))->toContain('ora_app_cpi');
    }

    // The APL time is the client's exception: three regions, nowhere else.
    foreach (['GOL - Lombardia', 'GOL - Lazio', 'GOL - Sicilia'] as $region) {
        expect(effectiveOpportunityCodes($region))->toContain('ora_app_apl');
    }

    expect(effectiveOpportunityCodes('GOL - Molise'))->not->toContain('ora_app_apl');

    // Neither time leaks outside the GOL branch.
    expect(effectiveOpportunityCodes('Formazione'))->not->toContain('ora_app_cpi')
        ->and(effectiveOpportunityCodes('Autofinanziato'))->not->toContain('ora_app_cpi');
});

it('places each appointment time right under its own date', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $service = app(AttributeLayoutService::class);
    $rowsOf = function (string $categoryName) use ($service): array {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
        $blob = $service->resolveExact($category, AttributeContext::Opportunity, LayoutFormScope::All);

        return array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $blob['sections'][0]['rows'],
        );
    };

    // A region with both appointments: dates row, then times row, aligned.
    expect(array_slice($rowsOf('GOL - Lazio'), 0, 2))->toBe([
        ['data_scelta_cpi', 'data_app_apl'],
        ['ora_app_cpi', 'ora_app_apl'],
    ]);

    // A region with only the CPI one keeps it alone, still in the first column.
    expect(array_slice($rowsOf('GOL - Molise'), 0, 2))->toBe([
        ['data_scelta_cpi', 'data_app_apl'],
        ['ora_app_cpi'],
    ]);

    // Outside the GOL branch the times row drops entirely.
    expect($rowsOf('Autofinanziato'))->not->toContain(['ora_app_cpi']);
});

it('retires "Corso di interesse" from every category without deleting the imported row', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $corso = Attribute::query()->where('code', 'corso')->first();

    // The q-crm import is not simulated here: on a clean database the code the
    // catalogue stopped declaring is simply never created.
    expect($corso)->toBeNull();

    // Re-created as the import would leave it, and put back on the category an
    // earlier revision of the catalogue assigned it to.
    $corso = Attribute::query()->create(['code' => 'corso', 'name' => 'Corso scelto', 'type' => 'text']);
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $formazione->attributes()->attach($corso->id, [
        'context' => AttributeContext::Opportunity->value,
        'is_required' => false,
        'sort_order' => 0,
    ]);

    $service = app(AttributeLayoutService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $service->upsert($molise, AttributeContext::Opportunity, LayoutFormScope::All, [
        'sections' => [[
            'id' => 'legacy',
            'title' => 'Dati Lavorazione Contatto',
            'description' => null,
            'variant' => 'default',
            'collapsible' => false,
            'default_collapsed' => false,
            'columns' => 2,
            'sort_order' => 0,
            'rows' => [
                ['id' => 'legacy-0', 'items' => [['attribute_code' => 'corso', 'width' => 'half']]],
                ['id' => 'legacy-1', 'items' => [['attribute_code' => 'cpi', 'width' => 'half']]],
            ],
        ]],
    ]);

    test()->seed(QualificaContactProcessingSeeder::class);

    // The row survives (it belongs to the import), the assignments do not.
    expect(Attribute::query()->where('code', 'corso')->exists())->toBeTrue()
        ->and(DB::table('attribute_category')->where('attribute_id', $corso->id)->count())->toBe(0)
        ->and(effectiveOpportunityCodes('GOL - Molise'))->not->toContain('corso');

    // The stale layout item goes with it, or the next save would 422.
    $blob = $service->resolveExact($molise, AttributeContext::Opportunity, LayoutFormScope::All);
    expect($blob['sections'][0]['rows'])->toHaveCount(1)
        ->and(array_column($blob['sections'][0]['rows'][0]['items'], 'attribute_code'))->toBe(['cpi']);
});

it('adopts the q-crm row instead of minting a parallel one', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The reused codes stay ONE attribute each, with the label the legacy
    // system gave them (user decision: keep code and label).
    foreach (['cpi' => 'CPI', 'data_app_apl' => 'OK app. APL', 'id_corso' => 'ID Corso'] as $code => $name) {
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
