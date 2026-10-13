<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\QualificaCatalog\ECampusAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ECampusCourseCatalogue;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// The e-Campus degree catalogue: "Corsi E-Campus" under Formazione, every
// product of each course filed on it directly (user directive 2026-10-02),
// and the fold of the three-level tree an earlier revision seeded.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/** @return array<string, float> product name => price */
function eCampusProductsOf(ProductCategory $category): array
{
    return Product::query()
        ->where('category_id', $category->id)
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (Product $product): array => [$product->name => (float) $product->price])
        ->all();
}

/**
 * @param  list<array<string, mixed>>  $sections
 * @return list<list<string>> every row's codes, in reading order
 */
function eCampusLayoutCodes(array $sections): array
{
    return array_merge(...array_map(
        fn (array $section): array => array_map(fn (array $row): array => array_column($row['items'], 'attribute_code'), $section['rows']),
        $sections,
    ));
}

/**
 * The tree the 2026-10-01 revision seeded: Formazione > Corsi E-Campus >
 * degree level > "<area> - <degree level>".
 *
 * @param  list<string>  $areaNames
 * @return array{branch: ProductCategory, degree: ProductCategory, areas: list<ProductCategory>}
 */
function retiredECampusTree(array $areaNames): array
{
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'parent_id' => null, 'is_selectable' => false]);
    $branch = ProductCategory::factory()->create(['name' => ECampusCourseCatalogue::CATEGORY, 'parent_id' => $formazione->id, 'is_selectable' => false]);
    $degree = ProductCategory::factory()->create(['name' => 'Corsi di Laurea Triennali', 'parent_id' => $branch->id, 'is_selectable' => false]);
    $areas = array_map(
        fn (string $name): ProductCategory => ProductCategory::factory()->create(['name' => $name, 'parent_id' => $degree->id, 'is_selectable' => true]),
        $areaNames,
    );

    return ['branch' => $branch, 'degree' => $degree, 'areas' => $areas];
}

it('seeds every e-Campus degree fee directly on "Corsi E-Campus", idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: (name, category) keys, no duplicates.

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->sole();
    $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->sole();
    $products = Product::query()->where('category_id', $branch->id)->get();

    // A selectable leaf of Formazione: no degree level, no subject area.
    expect($branch->parent_id)->toBe($formazione->id)
        ->and($branch->is_selectable)->toBeTrue()
        ->and(ProductCategory::query()->where('parent_id', $branch->id)->exists())->toBeFalse();

    // The full offer line under the simplified Formazione (spec 0188): the
    // branch declares its own override, the only node of the catalogue that does.
    expect($formazione->simplified_offer_line)->toBeTrue()
        ->and($branch->simplified_offer_line_override)->toBeFalse()
        ->and($branch->simplified_offer_line)->toBeFalse()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('simplified_offer_line'))->toBeTruthy()
        ->and(ProductCategory::query()->whereNotNull('simplified_offer_line_override')->pluck('name')->all())
        ->toBe([ECampusCourseCatalogue::CATEGORY]);

    // 15 bachelor courses x 4 fees + 10 master courses x 3 fees, each named
    // code first and priced at its fee, plus the thesis and the tutoring sold
    // once for the whole branch (user directive 2026-10-08).
    expect($products)->toHaveCount(92)
        ->and($products->every(fn (Product $product): bool => $product->product_type === ProductType::Service
            && (float) $product->cost === 0.0
            && $product->vat_rate_id === null))->toBeTrue()
        ->and(array_slice(eCampusProductsOf($branch), 0, 4))->toBe([
            '[L-7] Ingegneria Civile e Ambientale 1°ANNO' => 2856.0,
            '[L-7] Ingegneria Civile e Ambientale 2°ANNO' => 2856.0,
            '[L-7] Ingegneria Civile e Ambientale 3°ANNO' => 2856.0,
            '[L-7] Ingegneria Civile e Ambientale FORM' => 1500.0,
        ])
        ->and(array_slice(eCampusProductsOf($branch), -5))->toBe([
            '[LM-56] Scienze dell\'Economia 1°ANNO' => 3056.0,
            '[LM-56] Scienze dell\'Economia 2°ANNO' => 3056.0,
            '[LM-56] Scienze dell\'Economia FORM' => 1500.0,
            'TESI' => 300.0,
            'ASSISTENZA E TUTORAGGIO' => 500.0,
        ]);
});

it('folds the retired degree and area nodes onto "Corsi E-Campus" with their products and lines', function (): void {
    ['branch' => $branch, 'degree' => $degree, 'areas' => [$area]] = retiredECampusTree(['Ingegneria - Corsi di Laurea Triennali']);
    $product = Product::factory()->create(['name' => 'Ingegneria Civile e Ambientale [L-7] 1°ANNO', 'category_id' => $area->id]);
    $line = OpportunityProductLine::factory()->create(['product_category_id' => $area->id]);

    test()->seed(QualificaCatalogSeeder::class);

    // The old nodes are gone, what they held now sits on the branch: the
    // product is moved and renamed, never duplicated, and the offer line follows it.
    expect(ProductCategory::query()->whereKey([$degree->id, $area->id])->exists())->toBeFalse()
        ->and($branch->fresh()->is_selectable)->toBeTrue()
        ->and($product->fresh()->only(['category_id', 'name']))->toBe(['category_id' => $branch->id, 'name' => '[L-7] Ingegneria Civile e Ambientale 1°ANNO'])
        ->and(Product::query()->where('name', '[L-7] Ingegneria Civile e Ambientale 1°ANNO')->count())->toBe(1)
        ->and($line->fresh()->product_category_id)->toBe($branch->id)
        ->and(Product::query()->where('category_id', $branch->id)->count())->toBe(92);
});

it('describes every e-Campus course product with its subject area, filling only an empty description', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->sole();
    $descriptions = Product::query()->where('category_id', $branch->id)->pluck('description', 'name');
    $singleProducts = array_keys(ECampusCourseCatalogue::SINGLE_PRODUCTS);

    // The sheet's grouping, e.g. a communication course filed under law; the
    // services sold for the whole branch belong to no area.
    expect($descriptions->except($singleProducts)->unique()->sort()->values()->all())
        ->toBe(['Economia', 'Giurisprudenza', 'Ingegneria', 'Letteratura', 'Psicologia'])
        ->and($descriptions->only($singleProducts)->filter()->all())->toBe([])
        ->and($descriptions['[L-20] Scienze della Comunicazione FORM'])->toBe('Giurisprudenza')
        ->and($descriptions['[LM-56] Scienze dell\'Economia 1°ANNO'])->toBe('Economia');

    // An earlier revision seeded no description; an operator wrote one.
    Product::query()->where('name', '[L-33] Economia 2°ANNO')->update(['description' => null]);
    Product::query()->where('name', '[LM-51] Psicologia 2°ANNO')->update(['description' => 'Testo manuale']);

    test()->seed(QualificaCatalogSeeder::class);

    expect(Product::query()->where('name', '[L-33] Economia 2°ANNO')->value('description'))->toBe('Economia')
        ->and(Product::query()->where('name', '[LM-51] Psicologia 2°ANNO')->value('description'))->toBe('Testo manuale');
});

it('renames the trailing-code e-Campus products, keeping their id and records', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // An earlier revision's names: one picked on an opportunity, one whose
    // new name an operator already created by hand.
    $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->sole();
    Product::query()->where('category_id', $branch->id)->whereIn('name', ['[L-33] Economia 1°ANNO', '[LM-51] Psicologia FORM'])->delete();
    $picked = Product::factory()->create(['name' => 'Economia [L-33] 1°ANNO', 'category_id' => $branch->id]);
    Opportunity::factory()->create()->productsOfInterest()->attach($picked->id);
    Product::factory()->create(['name' => '[LM-51] Psicologia 2°ANNO', 'category_id' => $branch->id]);
    $clashing = Product::factory()->create(['name' => 'Psicologia [LM-51] 2°ANNO', 'category_id' => $branch->id]);

    test()->seed(QualificaCatalogSeeder::class);

    expect($picked->fresh()->name)->toBe('[L-33] Economia 1°ANNO')
        ->and(DB::table('opportunity_product')->where('product_id', $picked->id)->exists())->toBeTrue()
        ->and(Product::query()->where('name', '[L-33] Economia 1°ANNO')->count())->toBe(1)
        ->and($clashing->fresh()->name)->toBe('Psicologia [LM-51] 2°ANNO')
        ->and(Product::query()->where('name', '[LM-51] Psicologia FORM')->count())->toBe(1);
});

it('withdraws the retired per-course fees, keeping the one already referenced', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Earlier revisions' products, old and new names, one already picked on
    // an opportunity.
    $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->sole();
    $unused = collect(['Economia [L-33] PROGETTO FORM', 'Economia [L-33] TESI', '[L-33] Economia ASSISTENZA E TUTORAGGIO'])
        ->map(fn (string $name): Product => Product::factory()->create(['name' => $name, 'category_id' => $branch->id]));
    $picked = Product::factory()->create(['name' => 'Psicologia [LM-51] TESI', 'category_id' => $branch->id]);
    Opportunity::factory()->create()->productsOfInterest()->attach($picked->id);

    test()->seed(QualificaCatalogSeeder::class);

    expect(Product::query()->whereKey($unused->pluck('id'))->exists())->toBeFalse()
        ->and($picked->fresh()->name)->toBe('[LM-51] Psicologia TESI')
        ->and(Product::query()->where('category_id', $branch->id)->whereIn('name', array_keys(ECampusCourseCatalogue::SINGLE_PRODUCTS))->count())->toBe(2)
        ->and(Attribute::query()->where('code', 'form_course')->value('type'))->toBe('boolean');
});

it('refuses a fold that would merge two lines of one opportunity, leaving the old tree whole', function (): void {
    ['areas' => [$engineering, $economics]] = retiredECampusTree([
        'Ingegneria - Corsi di Laurea Triennali',
        'Economia - Corsi di Laurea Triennali',
    ]);
    $line = OpportunityProductLine::factory()->create(['product_category_id' => $engineering->id]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $line->opportunity_id,
        'business_function_id' => $line->business_function_id,
        'product_category_id' => $economics->id,
    ]);

    expect(fn () => test()->seed(QualificaCatalogSeeder::class))
        ->toThrow(RuntimeException::class, 'opportunity_product_lines');

    expect(ProductCategory::query()->whereKey([$engineering->id, $economics->id])->count())->toBe(2)
        ->and($line->fresh()->product_category_id)->toBe($engineering->id);
});

it('gives the e-Campus branch its own offer form, layout and working states', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->sole();
    $hierarchy = app(CategoryHierarchy::class);
    $ownCodes = array_column(ECampusAttributeCatalogue::ATTRIBUTES, 'code');

    // Behind a barrier: the branch resolves the e-Campus fields and nothing
    // of the Formazione set ("Ore complessive", CPI, "Dati Aula").
    expect($branch->inherits_quote_attributes)->toBeFalse()
        ->and($hierarchy->effectiveAttributes($branch, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
        ->toBe(collect($ownCodes)->sort()->values()->all());

    $faculty = Attribute::query()->where('code', 'faculty')->sole();
    expect($faculty->type)->toBe('enum')
        ->and($faculty->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Psicologia', 'Economia', 'Giurisprudenza', 'Ingegneria', 'Lettere']);

    // The styled form sits on the branch the offers are classified on.
    $layouts = app(AttributeLayoutService::class);
    $sections = $layouts->resolveWithFallback($branch, AttributeContext::Quote, FormMode::Create)['sections'];

    expect($layouts->resolveExact($branch, AttributeContext::Quote, LayoutFormScope::All))->not->toBeNull()
        ->and(array_column($sections, 'title'))->toBe(['Corso di Laurea', 'Documenti di iscrizione', 'Pagamento'])
        ->and(array_column($sections, 'variant'))->toBe(['default', 'default', 'default'])
        ->and(array_column($sections, 'columns'))->toBe([2, 2, 1])
        // No course field (user directive 2026-10-02): the course is the
        // product. Every field fills its row.
        ->and($sections[0]['rows'])->toHaveCount(2)
        ->and(array_column($sections[0]['rows'][0]['items'], 'width'))->toBe(['half', 'half'])
        ->and(eCampusLayoutCodes($sections))->toBe([
            ['faculty', 'degree_level'],
            ['form_course'],
            ['identification_documents', 'enrollment_form'],
            ['diploma_or_self_certification', 'annex_a'],
            ['ecampus_receipt', 'qualifica_receipt'],
            ['data_collection_form'],
            ['payment_type'],
        ])
        ->and(array_column($sections[2]['rows'][0]['items'], 'width'))->toBe(['full']);

    // The payment flags gave way to the select, "Finanziamento" among its options.
    $paymentType = Attribute::query()->where('code', 'payment_type')->sole();
    expect($paymentType->type)->toBe('enum')
        ->and($paymentType->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Unica soluzione', '2 rate', '3 rate', 'Finanziamento']);

    // One working-state set on the whole branch, the sheet's states between
    // the pinned system rows.
    $workflow = QuoteWorkflow::query()->where('name', ECampusCourseCatalogue::CATEGORY)->with('criteria')->sole();
    $statuses = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->orderBy('sort_order')->get();

    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe(WorkflowStatusCatalogue::BRANCH_CRITERION_FIELD)
        ->and($workflow->criteria->first()->value_id)->toBe($branch->id)
        ->and($statuses)->toHaveCount(14)
        ->and($statuses->first()->only(['name', 'system_key']))->toBe(['name' => 'Nuovo Contatto', 'system_key' => 'open'])
        ->and($statuses->slice(-2)->map->only(['name', 'system_key'])->values()->all())->toBe([
            ['name' => 'ISCRITTO', 'system_key' => 'closed_won'],
            ['name' => 'Non attinente', 'system_key' => 'closed_lost'],
        ])
        ->and($statuses->firstWhere('name', 'Attesa Prevalutazione')->group->value)->toBe('pending')
        ->and($statuses->firstWhere('name', 'Non risponde')->group->value)->toBe('open')
        ->and($statuses->pluck('name')->all())->not->toContain('NR');
});

it('recomposes the e-Campus form an installation got from the 2026-10-01 revision', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // That revision's state: its retired fields assigned on the branch and
    // its form, composed exactly as the seeder wrote it.
    $branch = ProductCategory::query()->where('name', ECampusCourseCatalogue::CATEGORY)->sole();
    $retired = ['degree_course' => 'text', 'fee_regulation' => 'boolean', 'bank_transfer' => 'boolean', 'financing' => 'boolean'];
    foreach ($retired as $code => $type) {
        Attribute::factory()->create(['code' => $code, 'type' => $type])
            ->categories()->attach($branch->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);
    }
    $composer = new class
    {
        use SeedsAttributeLayouts;

        /** @return list<array<string, mixed>> */
        public function sections(array $definitions): array
        {
            return array_map(fn (array $definition, int $order): array => $this->layoutSection(...[...array_slice($definition, 0, 3), $order, $definition[3]]), $definitions, array_keys($definitions));
        }
    };
    AttributeLayout::query()->where('product_category_id', $branch->id)->where('context', AttributeContext::Quote->value)->sole()
        ->update(['layout' => ['sections' => $composer->sections(ECampusAttributeCatalogue::PREVIOUS_SECTIONS)]]);

    test()->seed(QualificaCatalogSeeder::class);

    $effective = app(CategoryHierarchy::class)->effectiveAttributes($branch, AttributeContext::Quote)->pluck('code');
    $sections = app(AttributeLayoutService::class)->resolveWithFallback($branch, AttributeContext::Quote, FormMode::Create)['sections'];

    expect($effective->intersect(array_keys($retired)))->toBeEmpty()
        ->and(eCampusLayoutCodes($sections))->toBe(array_merge(...array_column(ECampusAttributeCatalogue::SECTIONS, 2)))
        ->and(array_column($sections[2]['rows'][0]['items'], 'width'))->toBe(['full']);
});
