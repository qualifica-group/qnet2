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
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// The client's hard-coded reference data, split out of QualificaTemplateSeeder
// (which now provisions custom field STRUCTURE only).
uses(RefreshDatabase::class);

/**
 * Every product the catalogue seeds: the GOL courses, the self-funded ones and
 * one per CatalogProducts::SINGLE_OFFER_CATEGORIES.
 */
const TOTAL_SEEDED_PRODUCTS = 265;

it('provisions the client source catalogue, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate, no duplicates.

    $expected = [
        'Diretto', 'Passaparola', 'Diretto / Passaparola', 'Social', 'Sito', 'Spoki',
        'Centralino', 'In Sede', 'Segnalatore', 'Spontaneo',
    ];

    expect(Source::query()->whereIn('name', $expected)->count())->toBe(count($expected));
    expect(Source::query()->count())->toBe(count($expected));
});

it('provisions the client reward type catalogue, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate, no duplicates.

    expect(RewardType::query()->where('name', 'Buono Amazon')->count())->toBe(1)
        ->and(RewardType::query()->where('name', 'Buono Amazon')->value('color'))->toBe('orange');
});

it('provisions the reference category catalogue tree, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate, no duplicates.

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

    foreach (['Trattative in Corso', 'Presa Appuntamenti'] as $name) {
        expect(ProductCategory::query()->where('name', $name)->where('parent_id', $consulenza->id)->count())->toBe(1);
    }
});

it('provisions the regional GOL declinations as children of the GOL subcategory', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate, no duplicates.

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
});

it('seeds "APL" as a root of its own, with its offer one level down (user directive 2026-09-07)', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate, no duplicates.

    $apl = ProductCategory::query()->where('name', 'APL')->firstOrFail();
    $offer = ProductCategory::query()->where('name', 'Orientamento Specialistico')->firstOrFail();

    // A branch of its own, never under "Consulenza".
    expect($apl->parent_id)->toBeNull()
        // A root groups, it never hosts an offer itself.
        ->and($apl->is_selectable)->toBeFalsy()
        ->and(Product::query()->where('category_id', $apl->id)->exists())->toBeFalse()
        // The offer sits one level down, where the product is filed.
        ->and($offer->parent_id)->toBe($apl->id)
        ->and($offer->is_selectable)->toBeTruthy()
        ->and(Product::query()->where('category_id', $offer->id)->pluck('name')->all())
        ->toBe(['Orientamento Specialistico']);
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

it('seeds the first two catalogue levels as containers, third level only selectable (spec 0074)', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    // Roots AND every subcategory under them, whether or not they already have
    // children: the catalogue classifies on its third level (user directive
    // 2026-08-03). The declared exceptions below are the subcategories that
    // host their own offer.
    $containers = [
        'Formazione', 'Consulenza',
        'GOL', 'DIL', 'APL',
        'Trattative in Corso', 'Presa Appuntamenti',
    ];
    foreach ($containers as $name) {
        expect(ProductCategory::query()->where('name', $name)->value('is_selectable'))
            ->toBeFalsy(sprintf('"%s" is a catalogue container: it must not be selectable.', $name));
    }

    // The classification targets the catalogue seeds today: the regional GOL
    // leaves, plus the subcategories hosting their own offer.
    $selectable = ProductCategory::query()->where('is_selectable', true)->pluck('name')->sort()->values()->all();
    expect($selectable)->toBe([
        'Autofinanziato',
        'Autoimpiego',
        'GOL - Abruzzo', 'GOL - Basilicata', 'GOL - Calabria', 'GOL - Campania',
        'GOL - Lazio', 'GOL - Lombardia', 'GOL - Molise', 'GOL - Puglia',
        'GOL - Sicilia', 'GOL - Umbria',
        'Orientamento Specialistico',
        'Yisu',
    ]);
});

it('seeds "Autofinanziato" as a classification target, it hosts the self-funded courses itself', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();

    expect($autofinanziato->is_selectable)->toBeTrue()
        // The reason it must be one: its products are filed directly on it.
        ->and(Product::query()->where('category_id', $autofinanziato->id)->count())
        ->toBe(count(SelfFundedCourseCatalogue::COURSES));
});

it('seeds one product named after each single-offer category, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural key (name, category), no duplicates.

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

it('realigns "Autofinanziato" back to selectable on an installation that seeded it as a container', function (): void {
    // The state left by the previous version of this seeder, which classified
    // every subcategory as a container.
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione', 'is_selectable' => false]);
    ProductCategory::factory()->create(['name' => 'Autofinanziato', 'parent_id' => $formazione->id, 'is_selectable' => false]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'Autofinanziato')->value('is_selectable'))->toBeTruthy();
});

it('never re-selects a third-level node an operator has deliberately turned into a container', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    ProductCategory::query()->where('name', 'GOL - Molise')->update(['is_selectable' => false]);

    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductCategory::query()->where('name', 'GOL - Molise')->value('is_selectable'))->toBeFalsy();
});

it('assigns the "Ore complessive" offer attribute to the whole Formazione branch', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicate attribute nor pivot row.

    $attribute = Attribute::query()->where('code', 'total_hours')->get();

    expect($attribute)->toHaveCount(1)
        ->and($attribute->first()->name)->toBe('Ore complessive')
        ->and($attribute->first()->type)->toBe('integer');

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    // One single assignment, on the root, in the OFFERTA context (user
    // directive 2026-09-08: it used to be a product attribute).
    $pivot = DB::table('attribute_category')->where('attribute_id', $attribute->first()->id)->get();

    expect($pivot)->toHaveCount(1)
        ->and($pivot->first()->category_id)->toBe($formazione->id)
        ->and($pivot->first()->context)->toBe(AttributeContext::Quote->value);

    // Inherited all the way down: subcategory and regional grandchild resolve it.
    $service = app(ProductCategoryService::class);

    foreach (['GOL', 'GOL - Molise', 'DIL'] as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();
        $effective = $service->effectiveAttributes($category, AttributeContext::Quote);

        expect($effective->pluck('code')->all())->toContain('total_hours')
            ->and($effective->firstWhere('code', 'total_hours')['inherited'])->toBeTrue($name);
    }

    // Not leaked onto the other root, and gone from the product context.
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();
    expect($service->effectiveAttributes($consulenza, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('total_hours')
        ->and($service->effectiveAttributes($formazione, AttributeContext::Product)->pluck('code')->all())
        ->not->toContain('total_hours');
});

it('assigns the "Dati Aula" offer attributes to the Formazione root', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicate attribute, option nor pivot row.

    $codes = ClassroomAttributeCatalogue::codes();
    $attributes = Attribute::query()->whereIn('code', $codes)->get()->keyBy('code');

    expect($attributes)->toHaveCount(count($codes))
        ->and($attributes->get('classroom_status')->name)->toBe('Stato Aula')
        ->and($attributes->get('course_start_date')->type)->toBe('date')
        ->and($attributes->get('internship_company')->type)->toBe('text');

    // The teacher is a relation to a single referent.
    expect($attributes->get('teacher')->type)->toBe('relation')
        ->and($attributes->get('teacher')->relation_target)->toBe([
            'entity_type' => 'referents',
            'cardinality' => 'one',
            'for_select_resource' => 'referents',
        ]);

    expect($attributes->get('classroom_status')->options()->get()->map->only(['value', 'label'])->all())
        ->toBe([
            ['value' => 'open', 'label' => 'Aperta'],
            ['value' => 'closed', 'label' => 'Chiusa'],
        ]);

    // One single assignment each, on the root, in the OFFERTA context (user
    // directive 2026-09-08: they used to be product attributes).
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    $pivot = DB::table('attribute_category')->whereIn('attribute_id', $attributes->pluck('id'))->get();

    expect($pivot)->toHaveCount(count($codes))
        ->and($pivot->pluck('category_id')->unique()->all())->toBe([$formazione->id])
        ->and($pivot->pluck('context')->unique()->all())->toBe([AttributeContext::Quote->value]);

    // Inherited down the branch, not leaked onto the other root, and gone from
    // the product context.
    $service = app(ProductCategoryService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();

    expect($service->effectiveAttributes($molise, AttributeContext::Quote)->pluck('code')->all())
        ->toContain(...$codes)
        ->and($service->effectiveAttributes($consulenza, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('teacher')
        ->and($service->effectiveAttributes($molise, AttributeContext::Product)->pluck('code')->all())
        ->not->toContain('teacher');
});

it('seeds every GOL training course under its own region, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural key (name, category), no duplicates.

    $expectedPerRegion = [
        'GOL - Molise' => 14, 'GOL - Abruzzo' => 54, 'GOL - Calabria' => 9,
        'GOL - Campania' => 68, 'GOL - Lombardia' => 64, 'GOL - Lazio' => 35,
        'GOL - Umbria' => 8,
    ];

    foreach ($expectedPerRegion as $categoryName => $count) {
        $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

        expect(Product::query()->where('category_id', $category->id)->count())->toBe($count, $categoryName);
    }

    // Outside the regions: the self-funded courses, plus the one product of
    // each single-offer category ("Autoimpiego", "Yisu", "Orientamento
    // Specialistico").
    expect(Product::query()->count())
        ->toBe(array_sum($expectedPerRegion) + count(SelfFundedCourseCatalogue::COURSES) + count(CatalogProducts::SINGLE_OFFER_CATEGORIES));
});

it('files each course with no attribute value of its own', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $course = Product::query()->where('name', 'Alfabetizzazione Digitale')->where('category_id', $molise->id)->firstOrFail();

    // User directive 2026-09-08: the duration moved to the Offerta, so the
    // product carries nothing — writing it would now be rejected outright,
    // the code being outside the product's applicable set.
    expect($course->attribute_values)->toBeEmpty()
        ->and($course->product_type)->toBe(ProductType::Service)
        // Cost/price are filled in later through the CRUD modules.
        ->and((float) $course->cost)->toBe(0.0)
        ->and((float) $course->price)->toBe(0.0);

    expect(Product::query()->get()->filter(fn (Product $product): bool => filled($product->attribute_values)))
        ->toBeEmpty();
});

it('keeps a course name repeated inside one region as two distinct products', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $abruzzo = ProductCategory::query()->where('name', 'GOL - Abruzzo')->firstOrFail();

    // The 7 Abruzzo duplicates are disambiguated by their duration...
    foreach ([['Magazziniere', 66, 260], ['Aiuto Cuoco', 50, 463], ['Pizzaiolo', 60, 370]] as [$name, $short, $long]) {
        expect(Product::query()->where('name', $name)->where('category_id', $abruzzo->id)->exists())->toBeFalse($name)
            ->and(Product::query()->where('name', "{$name} ({$short} ore)")->where('category_id', $abruzzo->id)->exists())->toBeTrue($name)
            ->and(Product::query()->where('name', "{$name} ({$long} ore)")->where('category_id', $abruzzo->id)->exists())->toBeTrue($name);
    }

    // ...while a name occurring once keeps it untouched.
    expect(Product::query()->where('name', 'Barista')->where('category_id', $abruzzo->id)->exists())->toBeTrue();
});

it('keeps the same course name in different regions as separate products', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $courses = Product::query()->where('name', 'Italiano per Stranieri')->with('category')->get();

    expect($courses->pluck('category.name')->sort()->values()->all())
        ->toBe(['GOL - Lazio', 'GOL - Lombardia', 'GOL - Molise']);
});

it('assigns the "Modalità di svolgimento" enum to the Autofinanziato subcategory only', function (): void {
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

    // One single assignment, on the subcategory, in the OFFERTA context.
    $pivot = DB::table('attribute_category')->where('attribute_id', $attribute->first()->id)->get();

    expect($pivot)->toHaveCount(1)
        ->and($pivot->first()->category_id)->toBe($autofinanziato->id)
        ->and($pivot->first()->context)->toBe(AttributeContext::Quote->value);

    // Confined to its own subtree: a sibling of Autofinanziato does not see it,
    // while the branch attribute assigned higher up still reaches both.
    $service = app(ProductCategoryService::class);

    expect($service->effectiveAttributes($autofinanziato, AttributeContext::Quote)->pluck('code')->all())
        ->toContain('delivery_mode')
        ->toContain('total_hours');

    $dil = ProductCategory::query()->where('name', 'DIL')->firstOrFail();

    expect($service->effectiveAttributes($dil, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('delivery_mode');
});

it('seeds the self-funded courses with their list price, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: natural key (name, category), no duplicates.

    $autofinanziato = ProductCategory::query()->where('name', 'Autofinanziato')->firstOrFail();

    expect(Product::query()->where('category_id', $autofinanziato->id)->count())
        ->toBe(count(SelfFundedCourseCatalogue::COURSES));

    $oss = Product::query()
        ->where('name', 'OSS - Operatore Socio Sanitario')
        ->where('category_id', $autofinanziato->id)
        ->firstOrFail();

    expect($oss->product_type)->toBe(ProductType::Service)
        ->and((float) $oss->price)->toBe(1900.0)
        // Cost is filled in later through the CRUD modules.
        ->and((float) $oss->cost)->toBe(0.0)
        // Duration and delivery mode are the Offerta's now.
        ->and($oss->attribute_values)->toBeEmpty();

    $aggiornamento = Product::query()
        ->where('name', 'Aggiornamento ASO')
        ->where('category_id', $autofinanziato->id)
        ->firstOrFail();

    expect((float) $aggiornamento->price)->toBe(130.0)
        ->and($aggiornamento->attribute_values)->toBeEmpty();
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
