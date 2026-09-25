<?php

use App\DataObjects\QuoteWorkflows\UpdateQuoteWorkflowData;
use App\Enums\AttributeContext;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\VatRate;
use App\Services\ProductCategoryService;
use App\Services\QuoteWorkflowService;
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// The self-funded ("Autofinanziato") courses, split per region by the user
// directive 2026-09-25: the container, its regional leaves, the list prices,
// the 22% VAT rate on the "+ iva" ones and the move of the courses an earlier
// revision filed on the container itself.
uses(RefreshDatabase::class);

function selfFundedProductsOf(string $categoryName): array
{
    $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

    return Product::query()->where('category_id', $category->id)->orderBy('name')->pluck('name')->all();
}

/** The same course name also runs as a GOL course: scope it to its region. */
function selfFundedProduct(string $categoryName, string $name): Product
{
    return Product::query()
        ->whereRelation('category', 'name', $categoryName)
        ->where('name', $name)
        ->sole();
}

it('seeds "Autofinanziato" as a container with one selectable leaf per region', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();
    $leaves = ProductCategory::query()->where('parent_id', $autofinanziato->id)->orderBy('name')->get();

    expect($autofinanziato->is_selectable)->toBeFalse()
        ->and(Product::query()->where('category_id', $autofinanziato->id)->count())->toBe(0)
        ->and($leaves->pluck('name')->all())->toBe(array_keys(SelfFundedCourseCatalogue::COURSES))
        ->and($leaves->every(fn (ProductCategory $leaf): bool => $leaf->is_selectable))->toBeTrue();
});

it('files each course once per region, never once per site, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural key (name, category), no duplicates.

    // The Sicilia sheet lists its six courses for each of nine sites, the
    // Lazio one its single course for three: one product each all the same.
    expect(selfFundedProductsOf('Autofinanziato - Campania'))->toHaveCount(10)
        ->and(selfFundedProductsOf('Autofinanziato - Lazio'))
        ->toBe(['Tecnico del comportamento Aba - Analisi comportamentale applicata'])
        ->and(selfFundedProductsOf('Autofinanziato - Lombardia'))
        ->toBe(['Meditazione e Respirazione Consapevole', 'Sarto'])
        ->and(selfFundedProductsOf('Autofinanziato - Sicilia'))->toBe([
            'ASACOM (Assistente all\'autonomia ed alla comunicazione dei disabili)',
            'ASO (Assistente studio odontoiatrico)',
            'Addetto amministrativo segretariale',
            'Assistente alla struttura educativa',
            'OSA (Operatore Socio Assistenziale)',
            'Security',
        ]);
});

it('seeds each course with its list price and no attribute value of its own', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $oss = selfFundedProduct('Autofinanziato - Campania', 'OSS - Operatore Socio Sanitario');

    expect($oss->product_type)->toBe(ProductType::Service)
        ->and((float) $oss->price)->toBe(1900.0)
        // Cost is filled in later through the CRUD modules.
        ->and((float) $oss->cost)->toBe(0.0)
        // Duration and delivery mode are the Offerta's.
        ->and($oss->attribute_values)->toBeEmpty();

    // Sites quoting different prices: the lowest one, also the most frequent.
    expect((float) selfFundedProduct('Autofinanziato - Sicilia', 'ASO (Assistente studio odontoiatrico)')->price)->toBe(1200.0)
        ->and((float) selfFundedProduct('Autofinanziato - Lombardia', 'Sarto')->price)->toBe(400.0);
});

it('gives the 22% VAT rate to the courses quoted "+ iva" only, with their net price', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: the rate is created once.

    $rate = VatRate::query()->where('rate', SelfFundedCourseCatalogue::VAT_RATE)->sole();

    $plusVat = Product::query()
        ->whereIn('name', ['EIPASS', 'PEKIT Expert', 'Tecnico del comportamento Aba - Analisi comportamentale applicata'])
        ->get();

    expect($rate->name)->toBe(SelfFundedCourseCatalogue::VAT_RATE_NAME)
        ->and($plusVat)->toHaveCount(3)
        ->and($plusVat->pluck('vat_rate_id')->unique()->all())->toBe([$rate->id])
        ->and($plusVat->mapWithKeys(fn (Product $product): array => [$product->name => (float) $product->price])->all())
        ->toEqualCanonicalizing([
            'EIPASS' => 230.0,
            'PEKIT Expert' => 150.0,
            'Tecnico del comportamento Aba - Analisi comportamentale applicata' => 650.0,
        ])
        ->and(Product::query()->whereNotNull('vat_rate_id')->count())->toBe(3);
});

it('reuses a 22% VAT rate an operator already created, whatever its name', function (): void {
    $existing = VatRate::factory()->create(['name' => 'Ordinaria', 'rate' => 22.0]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(VatRate::query()->count())->toBe(1)
        ->and(Product::query()->where('name', 'EIPASS')->value('vat_rate_id'))->toBe($existing->id);
});

it('moves the courses an earlier revision filed on "Autofinanziato" onto their region', function (): void {
    // The state left by the previous revision: a selectable "Autofinanziato"
    // hosting the Campania courses, one of them edited by hand.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'is_selectable' => false]);
    $autofinanziato = ProductCategory::factory()->create(['name' => 'Autofinanziato', 'parent_id' => $formazione->id, 'is_selectable' => true]);
    $manualRate = VatRate::factory()->create(['name' => 'Esente', 'rate' => 0.0]);

    $eipass = Product::factory()->create(['name' => 'EIPASS', 'category_id' => $autofinanziato->id, 'price' => 999.0, 'vat_rate_id' => null]);
    $oss = Product::factory()->create(['name' => 'OSS - Operatore Socio Sanitario', 'category_id' => $autofinanziato->id, 'vat_rate_id' => $manualRate->id]);

    test()->seed(QualificaCatalogSeeder::class);

    $campania = ProductCategory::query()->where('name', 'Autofinanziato - Campania')->firstOrFail();
    $eipass->refresh();
    $oss->refresh();

    expect($autofinanziato->refresh()->is_selectable)->toBeFalse()
        ->and(Product::query()->where('category_id', $autofinanziato->id)->count())->toBe(0)
        // Moved, not duplicated: the manual price survives, the missing rate is filled.
        ->and(Product::query()->where('name', 'EIPASS')->count())->toBe(1)
        ->and($eipass->category_id)->toBe($campania->id)
        ->and((float) $eipass->price)->toBe(999.0)
        ->and(VatRate::query()->find($eipass->vat_rate_id)?->rate)->toEqual(SelfFundedCourseCatalogue::VAT_RATE)
        // A rate chosen by hand wins.
        ->and($oss->category_id)->toBe($campania->id)
        ->and($oss->vat_rate_id)->toBe($manualRate->id);
});

it('assigns the "Modalità di svolgimento" enum to the Autofinanziato subtree only', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicate attribute, option nor pivot row.

    $attribute = Attribute::query()->where('code', 'delivery_mode')->get();

    expect($attribute)->toHaveCount(1)
        ->and($attribute->first()->name)->toBe('Modalità di svolgimento')
        ->and($attribute->first()->type)->toBe('enum');

    expect($attribute->first()->options()->get()->map->only(['value', 'label'])->all())
        ->toBe([
            ['value' => 'in_person', 'label' => 'In presenza'],
            ['value' => 'online', 'label' => 'Online'],
        ]);

    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();

    // One single assignment, on the container, in the OFFERTA context.
    $pivot = DB::table('attribute_category')->where('attribute_id', $attribute->first()->id)->get();

    expect($pivot)->toHaveCount(1)
        ->and($pivot->first()->category_id)->toBe($autofinanziato->id)
        ->and($pivot->first()->context)->toBe(AttributeContext::Quote->value);

    // Inherited by every region, together with the branch attribute assigned
    // higher up; a sibling of Autofinanziato does not see it.
    $service = app(ProductCategoryService::class);

    foreach (array_keys(SelfFundedCourseCatalogue::COURSES) as $leafName) {
        $leaf = ProductCategory::query()->where('name', $leafName)->firstOrFail();

        expect($service->effectiveAttributes($leaf, AttributeContext::Quote)->pluck('code')->all())
            ->toContain('delivery_mode')
            ->toContain('total_hours');
    }

    $dil = ProductCategory::query()->where('name', 'DIL')->firstOrFail();

    expect($service->effectiveAttributes($dil, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('delivery_mode');
});

it('matches the "Autofinanziato" working states on its whole branch, realigning an earlier exact match', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The state the previous revision left: the workflow on the exact category.
    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();
    $workflow = QuoteWorkflow::query()->where('name', 'Autofinanziato')->firstOrFail();
    app(QuoteWorkflowService::class)->update($workflow, new UpdateQuoteWorkflowData(criteria: [
        ['field' => WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD, 'value_id' => $autofinanziato->id],
    ]));

    test()->seed(QualificaCatalogSeeder::class);

    // The regional leaves are reached only through the branch criterion.
    $criterion = QuoteWorkflow::query()->where('name', 'Autofinanziato')->with('criteria')->sole()->criteria->sole();

    expect($criterion->field)->toBe(WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD)
        ->and($criterion->value_id)->toBe($autofinanziato->id);
});
