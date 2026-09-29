<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\MassMigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategoryService;
use App\Services\UserService;
use Database\Seeders\QualificaCatalog\CatalogProducts;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\DilCourseCatalogue;
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// The client's hard-coded reference data, split out of QualificaTemplateSeeder
// (which now provisions custom field STRUCTURE only).
uses(RefreshDatabase::class);

/**
 * Every product the catalogue seeds: the GOL and DIL courses, the self-funded
 * ones and one per CatalogProducts::SINGLE_OFFER_CATEGORIES.
 */
const TOTAL_SEEDED_PRODUCTS = 303;

/**
 * One scenario instead of one test per property: the catalogue seeder costs
 * ~1s per run and every check below used to pay for it separately. Reads
 * that only need a single seed run first, then the seeder re-runs once and
 * every idempotency check reads that final state — exactly what each former
 * test asserted, against the same state it originally asserted it against.
 */
it('provisions the whole reference catalogue correctly and idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Container levels (was: 'seeds the first two catalogue levels as containers, third level only selectable (spec 0074)')
    $containers = [
        'Formazione', 'Consulenza',
        'GOL', 'APL', 'DIL',
    ];
    foreach ($containers as $name) {
        expect(ProductCategory::query()->where('name', $name)->value('is_selectable'))
            ->toBeFalsy(sprintf('"%s" is a catalogue container: it must not be selectable.', $name));
    }

    $selectable = ProductCategory::query()->where('is_selectable', true)->pluck('name')->sort()->values()->all();
    expect($selectable)->toBe([
        'Autofinanziato - Campania', 'Autofinanziato - Lazio',
        'Autofinanziato - Lombardia', 'Autofinanziato - Sicilia',
        'Autoimpiego',
        'DIL - Lombardia',
        'GOL - Abruzzo', 'GOL - Basilicata', 'GOL - Calabria', 'GOL - Campania',
        'GOL - Lazio', 'GOL - Lombardia', 'GOL - Molise', 'GOL - Puglia',
        'GOL - Sicilia', 'GOL - Umbria',
        'Orientamento Specialistico',
        // The Consulenza leaf (user directive 2026-09-28).
        'Presa Appuntamenti',
        'Yisu',
    ]);

    // Course attribute purity (was: 'files each course with no attribute value of its own')
    $moliseForCourse = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $digitalLiteracy = Product::query()->where('name', 'Alfabetizzazione Digitale')->where('category_id', $moliseForCourse->id)->firstOrFail();

    expect($digitalLiteracy->attribute_values)->toBeEmpty()
        ->and($digitalLiteracy->product_type)->toBe(ProductType::Service)
        ->and((float) $digitalLiteracy->cost)->toBe(0.0)
        ->and((float) $digitalLiteracy->price)->toBe(0.0);

    expect(Product::query()->get()->filter(fn (Product $product): bool => filled($product->attribute_values)))
        ->toBeEmpty();

    // Duplicate course names within a region (was: 'keeps a course name repeated inside one region as two distinct products')
    $abruzzo = ProductCategory::query()->where('name', 'GOL - Abruzzo')->firstOrFail();
    foreach ([['Magazziniere', 66, 260], ['Aiuto Cuoco', 50, 463], ['Pizzaiolo', 60, 370]] as [$name, $short, $long]) {
        expect(Product::query()->where('name', $name)->where('category_id', $abruzzo->id)->exists())->toBeFalse($name)
            ->and(Product::query()->where('name', "{$name} ({$short} ore)")->where('category_id', $abruzzo->id)->exists())->toBeTrue($name)
            ->and(Product::query()->where('name', "{$name} ({$long} ore)")->where('category_id', $abruzzo->id)->exists())->toBeTrue($name);
    }
    expect(Product::query()->where('name', 'Barista')->where('category_id', $abruzzo->id)->exists())->toBeTrue();

    // Same course name across regions (was: 'keeps the same course name in different regions as separate products')
    $italianCourses = Product::query()->where('name', 'Italiano per Stranieri')->with('category')->get();
    expect($italianCourses->pluck('category.name')->sort()->values()->all())
        ->toBe(['GOL - Lazio', 'GOL - Lombardia', 'GOL - Molise']);

    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate/natural keys, no duplicates.

    // Sources catalogue (was: 'provisions the client source catalogue, idempotently')
    $expectedSources = [
        'Diretto', 'Passaparola', 'Diretto / Passaparola', 'Social', 'Sito', 'Spoki',
        'Centralino', 'In Sede', 'Segnalatore', 'Spontaneo',
    ];
    expect(Source::query()->whereIn('name', $expectedSources)->count())->toBe(count($expectedSources));
    expect(Source::query()->count())->toBe(count($expectedSources));

    // Reward types (was: 'provisions the client reward type catalogue, idempotently')
    expect(RewardType::query()->where('name', 'Buono Amazon')->count())->toBe(1)
        ->and(RewardType::query()->where('name', 'Buono Amazon')->value('color'))->toBe('orange');

    // Category tree roots (was: 'provisions the reference category catalogue tree, idempotently')
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->first();
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->first();
    $apl = ProductCategory::query()->where('name', 'APL')->whereNull('parent_id')->first();

    expect($formazione)->not->toBeNull()
        ->and($consulenza)->not->toBeNull()
        ->and($apl)->not->toBeNull();

    $formazioneSubs = ['GOL', 'Autoimpiego', 'Yisu', 'Autofinanziato', 'DIL'];
    foreach ($formazioneSubs as $name) {
        expect(ProductCategory::query()->where('name', $name)->where('parent_id', $formazione->id)->count())->toBe(1);
    }
    // REQUIREMENT CHANGED (user directive 2026-09-28): "Trattative in Corso"
    // is no longer seeded, "Presa Appuntamenti" is the branch's only leaf.
    expect(ProductCategory::query()->where('parent_id', $consulenza->id)->pluck('name')->all())
        ->toBe(['Presa Appuntamenti']);

    // GOL regions (was: 'provisions the regional GOL declinations as children of the GOL subcategory')
    $gol = ProductCategory::query()->where('name', 'GOL')->first();
    expect($gol)->not->toBeNull();

    $regions = [
        'GOL - Molise', 'GOL - Abruzzo', 'GOL - Calabria', 'GOL - Campania',
        'GOL - Lombardia', 'GOL - Lazio', 'GOL - Umbria',
        // No course list yet: the category exists, empty, until one is supplied.
        'GOL - Puglia', 'GOL - Basilicata', 'GOL - Sicilia',
    ];
    foreach ($regions as $name) {
        expect(ProductCategory::query()->where('name', $name)->where('parent_id', $gol->id)->count())->toBe(1);
    }
    expect(ProductCategory::query()->where('parent_id', $gol->id)->count())->toBe(count($regions));

    // APL as its own root (was: 'seeds "APL" as a root of its own, with its offer one level down (user directive 2026-09-07)')
    $aplRoot = ProductCategory::query()->where('name', 'APL')->firstOrFail();
    $aplOffer = ProductCategory::query()->where('name', 'Orientamento Specialistico')->firstOrFail();

    expect($aplRoot->parent_id)->toBeNull()
        ->and($aplRoot->is_selectable)->toBeFalsy()
        ->and(Product::query()->where('category_id', $aplRoot->id)->exists())->toBeFalse()
        ->and($aplOffer->parent_id)->toBe($aplRoot->id)
        ->and($aplOffer->is_selectable)->toBeTruthy()
        ->and(Product::query()->where('category_id', $aplOffer->id)->pluck('name')->all())
        ->toBe(['Orientamento Specialistico']);

    // Single-offer category products (was: 'seeds one product named after each single-offer category, idempotently')
    foreach (CatalogProducts::SINGLE_OFFER_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();

        // The reason it must be a classification target: its product is filed
        // directly on it.
        expect($category->is_selectable)->toBeTrue();

        $products = Product::query()->where('category_id', $category->id)->get();

        expect($products)->toHaveCount(1)
            ->and($products->first()->name)->toBe($name)
            ->and($products->first()->product_type)->toBe(ProductType::Service)
            ->and((float) $products->first()->price)->toBe(0.0)
            ->and((float) $products->first()->cost)->toBe(0.0);
    }

    // "Ore complessive" offer attribute (was: 'assigns the "Ore complessive" offer attribute to the whole Formazione branch')
    $totalHours = Attribute::query()->where('code', 'total_hours')->get();

    expect($totalHours)->toHaveCount(1)
        ->and($totalHours->first()->name)->toBe('Ore complessive')
        ->and($totalHours->first()->type)->toBe('integer');

    // One single assignment, on the root, in the OFFERTA context (user
    // directive 2026-09-08: it used to be a product attribute).
    $totalHoursPivot = DB::table('attribute_category')->where('attribute_id', $totalHours->first()->id)->get();

    expect($totalHoursPivot)->toHaveCount(1)
        ->and($totalHoursPivot->first()->category_id)->toBe($formazione->id)
        ->and($totalHoursPivot->first()->context)->toBe(AttributeContext::Quote->value);

    // Inherited all the way down: subcategory and regional grandchild resolve it.
    $categoryService = app(ProductCategoryService::class);

    foreach (['GOL', 'GOL - Molise'] as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();
        $effective = $categoryService->effectiveAttributes($category, AttributeContext::Quote);

        expect($effective->pluck('code')->all())->toContain('total_hours')
            ->and($effective->firstWhere('code', 'total_hours')['inherited'])->toBeTrue($name);
    }

    // ...except below the barrier: "DIL" opted out of the Offerta context
    // (CategoryInheritanceRules), so the root's fields stop at it.
    $dilForHours = ProductCategory::query()->where('name', 'DIL')->firstOrFail();

    expect($categoryService->effectiveAttributes($dilForHours, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('total_hours');

    // Not leaked onto the other root, and gone from the product context.
    expect($categoryService->effectiveAttributes($consulenza, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('total_hours')
        ->and($categoryService->effectiveAttributes($formazione, AttributeContext::Product)->pluck('code')->all())
        ->not->toContain('total_hours');

    // "Dati Aula" offer attributes (was: 'assigns the "Dati Aula" offer attributes to the Formazione root')
    $classroomCodes = ClassroomAttributeCatalogue::codes();
    $classroomAttributes = Attribute::query()->whereIn('code', $classroomCodes)->get()->keyBy('code');

    expect($classroomAttributes)->toHaveCount(count($classroomCodes))
        ->and($classroomAttributes->get('classroom_status')->name)->toBe('Stato Aula')
        ->and($classroomAttributes->get('course_start_date')->type)->toBe('date')
        ->and($classroomAttributes->get('internship_company')->type)->toBe('text');

    // The teacher is a relation to a single referent.
    expect($classroomAttributes->get('teacher')->type)->toBe('relation')
        ->and($classroomAttributes->get('teacher')->relation_target)->toBe([
            'entity_type' => 'referents',
            'cardinality' => 'one',
            'for_select_resource' => 'referents',
        ]);

    expect($classroomAttributes->get('classroom_status')->options()->get()->map->only(['value', 'label'])->all())
        ->toBe([
            ['value' => 'open', 'label' => 'Aperta'],
            ['value' => 'closed', 'label' => 'Chiusa'],
        ]);

    // One single assignment each, on the root, in the OFFERTA context (user
    // directive 2026-09-08: they used to be product attributes).
    $classroomPivot = DB::table('attribute_category')->whereIn('attribute_id', $classroomAttributes->pluck('id'))->get();

    expect($classroomPivot)->toHaveCount(count($classroomCodes))
        ->and($classroomPivot->pluck('category_id')->unique()->all())->toBe([$formazione->id])
        ->and($classroomPivot->pluck('context')->unique()->all())->toBe([AttributeContext::Quote->value]);

    // Inherited down the branch, not leaked onto the other root, and gone from
    // the product context.
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    expect($categoryService->effectiveAttributes($molise, AttributeContext::Quote)->pluck('code')->all())
        ->toContain(...$classroomCodes)
        ->and($categoryService->effectiveAttributes($consulenza, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('teacher')
        ->and($categoryService->effectiveAttributes($molise, AttributeContext::Product)->pluck('code')->all())
        ->not->toContain('teacher');

    // Self-employment flag (was: 'assigns the self-employment flag to "Autoimpiego" alone')
    $interestFlag = Attribute::query()->where('code', 'interest_expression')->get();
    $autoimpiego = ProductCategory::query()->where('name', 'Autoimpiego')->firstOrFail();

    // A tick box, not a pick list (user directive 2026-09-10).
    expect($interestFlag)->toHaveCount(1)
        ->and($interestFlag->first()->name)->toBe("Manifestazione d'Interesse")
        ->and($interestFlag->first()->type)->toBe('boolean');

    $interestPivot = DB::table('attribute_category')->where('attribute_id', $interestFlag->first()->id)->get();

    expect($interestPivot)->toHaveCount(1)
        ->and($interestPivot->first()->category_id)->toBe($autoimpiego->id)
        ->and($interestPivot->first()->context)->toBe(AttributeContext::Quote->value);

    // It shares the "Dati Aula" section but NOT the root assignment: no other
    // category of the branch resolves it.
    expect($categoryService->effectiveAttributes($autoimpiego, AttributeContext::Quote)->pluck('code')->all())
        ->toContain('interest_expression')
        ->and($categoryService->effectiveAttributes($formazione, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('interest_expression')
        ->and($categoryService->effectiveAttributes($molise, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('interest_expression');

    // GOL course counts (was: 'seeds every GOL training course under its own region, idempotently')
    $expectedPerRegion = [
        'GOL - Molise' => 14, 'GOL - Abruzzo' => 54, 'GOL - Calabria' => 9,
        'GOL - Campania' => 68, 'GOL - Lombardia' => 64, 'GOL - Lazio' => 35,
        'GOL - Umbria' => 8,
    ];

    foreach ($expectedPerRegion as $categoryName => $count) {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        expect(Product::query()->where('category_id', $category->id)->count())->toBe($count, $categoryName);
    }

    // Outside the GOL regions: the DIL courses, the self-funded ones, plus the
    // one product of each single-offer category.
    expect(Product::query()->count())
        ->toBe(array_sum($expectedPerRegion) + count(DilCourseCatalogue::COURSES['DIL - Lombardia'])
            + array_sum(array_map(count(...), SelfFundedCourseCatalogue::COURSES))
            + count(CatalogProducts::SINGLE_OFFER_CATEGORIES));
});

it('promotes "APL" out of "Consulenza" on an installation seeded while it hung there', function (): void {
    // The state left by the previous revision of this catalogue.
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza', 'parent_id' => null]);
    $apl = ProductCategory::factory()->create(['name' => 'APL', 'parent_id' => $consulenza->id]);

    test()->seed(QualificaCatalogSeeder::class);

    // `firstOrCreate` writes `parent_id` on creation only: without the
    // realignment the node would stay where the old revision put it.
    expect($apl->fresh()->parent_id)->toBeNull()
        ->and(ProductCategory::query()->where('name', 'APL')->count())->toBe(1);
});

it('realigns a container category seeded as selectable before the flag existed', function (): void {
    // The state of an installation seeded by the previous version: the tree is
    // already there, every node selectable.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'is_selectable' => true]);
    ProductCategory::factory()->create(['name' => 'GOL', 'parent_id' => $formazione->id, 'is_selectable' => true]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Formazione')->value('is_selectable'))->toBeFalsy()
        ->and(ProductCategory::query()->where('name', 'GOL')->value('is_selectable'))->toBeFalsy();
});

it('realigns the Consulenza leaf seeded as a container into a selectable one (user directive 2026-09-28)', function (): void {
    // The state of an installation seeded by the previous version: the leaf
    // already there, as a container.
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza', 'is_selectable' => false]);
    ProductCategory::factory()->create(['name' => 'Presa Appuntamenti', 'parent_id' => $consulenza->id, 'is_selectable' => false]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Presa Appuntamenti')->value('is_selectable'))->toBeTruthy()
        ->and(ProductCategory::query()->where('name', 'Consulenza')->value('is_selectable'))->toBeFalsy();
});

it('never re-selects a third-level node an operator has deliberately turned into a container', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    ProductCategory::query()->where('name', 'GOL - Molise')->update(['is_selectable' => false]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'GOL - Molise')->value('is_selectable'))->toBeFalsy();
});

it('withdraws the moved codes from the product side of an installation seeded earlier', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // The state the previous revision left: the same codes assigned in the
    // PRODUCT context, with the form section built on them.
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();

    foreach (Attribute::query()->whereIn('code', ['total_hours', 'teacher', 'exam_date'])->get() as $attribute) {
        $formazione->attributes()->attach($attribute->id, [
            'context' => AttributeContext::Product->value,
            'is_required' => false,
            'sort_order' => 0,
        ]);
    }

    $layouts = app(AttributeLayoutService::class);
    $layouts->upsert($molise, AttributeContext::Product, LayoutFormScope::All, [
        'sections' => [[
            'id' => 'classroom-data',
            'title' => 'Dati Aula',
            'description' => null,
            'variant' => 'default',
            'collapsible' => false,
            'default_collapsed' => false,
            'columns' => 2,
            'sort_order' => 0,
            'rows' => [['id' => 'classroom-data-0', 'items' => [
                ['attribute_code' => 'teacher', 'width' => 'half'],
                ['attribute_code' => 'exam_date', 'width' => 'half'],
            ]]],
        ]],
    ]);

    test()->seed(QualificaCatalogSeeder::class);

    // Gone from the product context — assignments AND the section built on
    // them, which is an emptied layout, hence a deleted row.
    expect(DB::table('attribute_category')->where('context', AttributeContext::Product->value)->count())->toBe(0)
        ->and($layouts->resolveExact($molise, AttributeContext::Product, LayoutFormScope::All))->toBeNull();

    // The Offerta side is untouched by the retirement: same codes, still there.
    $service = app(ProductCategoryService::class);
    expect($service->effectiveAttributes($molise, AttributeContext::Quote)->pluck('code')->all())
        ->toContain('total_hours', 'teacher', 'exam_date');

    // The attribute rows themselves survive: a code may be shared with the
    // q-crm import that owns it.
    expect(Attribute::query()->whereIn('code', ['total_hours', 'teacher', 'exam_date'])->count())->toBe(3);
});

// The follow-up prompt (user request): launched on its own, the seeder offers
// to chain the q-crm import. The seeders share one process, so the confirmation
// is driven through the artisan command, not through test()->seed().
it('offers the q-crm import and runs it when confirmed', function (): void {
    config(['migrations.base_url' => 'https://q-crm.test']);
    Http::fake(['https://q-crm.test/*' => Http::response(['items' => [], 'pagination' => ['total' => 0]])]);

    Role::query()->firstOrCreate(['name' => UserService::PRIVILEGED_ROLE]);
    User::factory()->create()->assignRole(UserService::PRIVILEGED_ROLE);

    test()->artisan('db:seed', ['--class' => QualificaCatalogSeeder::class])
        ->expectsConfirmation('Importare anche le tabelle di configurazione da q-crm?', 'yes')
        ->assertSuccessful();

    expect(MassMigrationRun::query()->count())->toBe(1);
});

it('seeds the catalogue and stops there when the import is declined', function (): void {
    config(['migrations.base_url' => 'https://q-crm.test']);
    Http::preventStrayRequests();

    test()->artisan('db:seed', ['--class' => QualificaCatalogSeeder::class])
        ->expectsConfirmation('Importare anche le tabelle di configurazione da q-crm?', 'no')
        ->assertSuccessful();

    expect(MassMigrationRun::query()->count())->toBe(0)
        ->and(Source::query()->count())->toBe(10);
});

it('does not ask when no external system is configured', function (): void {
    config(['migrations.base_url' => null]);
    Http::preventStrayRequests();

    // No expectsConfirmation: an unexpected prompt would fail the assertion
    // below, since the command would block on input it never receives.
    test()->artisan('db:seed', ['--class' => QualificaCatalogSeeder::class])->assertSuccessful();

    expect(MassMigrationRun::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(TOTAL_SEEDED_PRODUCTS);
});
