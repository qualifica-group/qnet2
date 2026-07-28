<?php

use App\Enums\AttributeContext;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\MassMigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Services\ProductCategoryService;
use App\Services\UserService;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// The client's hard-coded reference data, split out of QualificaTemplateSeeder
// (which now provisions custom field STRUCTURE only).
uses(RefreshDatabase::class);

it('provisions the client source catalogue, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: firstOrCreate, no duplicates.

    $expected = [
        'Diretto', 'Passaparola', 'Social', 'Sito', 'Spoki',
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

    expect($formazione)->not->toBeNull()
        ->and($consulenza)->not->toBeNull();

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

it('assigns the "Ore complessive" product attribute to the whole Formazione branch', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicate attribute nor pivot row.

    $attribute = Attribute::query()->where('code', 'total_hours')->get();

    expect($attribute)->toHaveCount(1)
        ->and($attribute->first()->name)->toBe('Ore complessive')
        ->and($attribute->first()->type)->toBe('integer');

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    // One single assignment, on the root, in the PRODUCT context.
    $pivot = DB::table('attribute_category')->where('attribute_id', $attribute->first()->id)->get();

    expect($pivot)->toHaveCount(1)
        ->and($pivot->first()->category_id)->toBe($formazione->id)
        ->and($pivot->first()->context)->toBe(AttributeContext::Product->value);

    // Inherited all the way down: subcategory and regional grandchild resolve it.
    $service = app(ProductCategoryService::class);

    foreach (['GOL', 'GOL - Molise', 'DIL'] as $name) {
        $category = ProductCategory::query()->where('name', $name)->firstOrFail();
        $effective = $service->effectiveAttributes($category, AttributeContext::Product);

        expect($effective->pluck('code')->all())->toContain('total_hours')
            ->and($effective->firstWhere('code', 'total_hours')['inherited'])->toBeTrue($name);
    }

    // Not leaked onto the other root.
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();
    expect($service->effectiveAttributes($consulenza, AttributeContext::Product)->pluck('code')->all())
        ->not->toContain('total_hours');
});

it('assigns the "Dati Aula" product attributes to the Formazione root', function (): void {
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

    // One single assignment each, on the root, in the PRODUCT context.
    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->firstOrFail();

    $pivot = DB::table('attribute_category')->whereIn('attribute_id', $attributes->pluck('id'))->get();

    expect($pivot)->toHaveCount(count($codes))
        ->and($pivot->pluck('category_id')->unique()->all())->toBe([$formazione->id])
        ->and($pivot->pluck('context')->unique()->all())->toBe([AttributeContext::Product->value]);

    // Inherited down the branch, not leaked onto the other root.
    $service = app(ProductCategoryService::class);
    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->firstOrFail();

    expect($service->effectiveAttributes($molise, AttributeContext::Product)->pluck('code')->all())
        ->toContain(...$codes)
        ->and($service->effectiveAttributes($consulenza, AttributeContext::Product)->pluck('code')->all())
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

    // Outside the regions, only the self-funded courses are seeded.
    expect(Product::query()->count())->toBe(array_sum($expectedPerRegion) + count(SelfFundedCourseCatalogue::COURSES));
});

it('files each course with its duration in the inherited "Ore complessive" attribute', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $molise = ProductCategory::query()->where('name', 'GOL - Molise')->firstOrFail();
    $course = Product::query()->where('name', 'Alfabetizzazione Digitale')->where('category_id', $molise->id)->firstOrFail();

    expect($course->attribute_values['total_hours'])->toEqual(60)
        ->and($course->product_type)->toBe(ProductType::Service)
        // Cost/price are filled in later through the CRUD modules.
        ->and((float) $course->cost)->toBe(0.0)
        ->and((float) $course->price)->toBe(0.0);

    // The 10 Lombardia rows quoting "150 / 140" keep the first value.
    $lombardia = ProductCategory::query()->where('name', 'GOL - Lombardia')->firstOrFail();
    $cuoco = Product::query()->where('name', 'Cuoco')->where('category_id', $lombardia->id)->firstOrFail();

    expect($cuoco->attribute_values['total_hours'])->toEqual(150);
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
        ->toBe(['GOL - Lazio', 'GOL - Lombardia', 'GOL - Molise'])
        // Same course, different regional duration: Lazio funds 50 hours, the other two 60.
        ->and($courses->firstWhere('category.name', 'GOL - Lazio')->attribute_values['total_hours'])->toEqual(50)
        ->and($courses->firstWhere('category.name', 'GOL - Molise')->attribute_values['total_hours'])->toEqual(60);
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

    // One single assignment, on the subcategory, in the PRODUCT context.
    $pivot = DB::table('attribute_category')->where('attribute_id', $attribute->first()->id)->get();

    expect($pivot)->toHaveCount(1)
        ->and($pivot->first()->category_id)->toBe($autofinanziato->id)
        ->and($pivot->first()->context)->toBe(AttributeContext::Product->value);

    // Confined to its own subtree: a sibling of Autofinanziato does not see it,
    // while the branch attribute assigned higher up still reaches both.
    $service = app(ProductCategoryService::class);

    expect($service->effectiveAttributes($autofinanziato, AttributeContext::Product)->pluck('code')->all())
        ->toContain('delivery_mode')
        ->toContain('total_hours');

    $dil = ProductCategory::query()->where('name', 'DIL')->firstOrFail();

    expect($service->effectiveAttributes($dil, AttributeContext::Product)->pluck('code')->all())
        ->not->toContain('delivery_mode');
});

it('seeds the self-funded courses with price, duration and delivery mode, idempotently', function (): void {
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
        ->and($oss->attribute_values['total_hours'])->toEqual(1000)
        ->and($oss->attribute_values['delivery_mode'])->toBe('in_person');

    $aggiornamento = Product::query()
        ->where('name', 'Aggiornamento ASO')
        ->where('category_id', $autofinanziato->id)
        ->firstOrFail();

    expect((float) $aggiornamento->price)->toBe(130.0)
        ->and($aggiornamento->attribute_values['total_hours'])->toEqual(10)
        ->and($aggiornamento->attribute_values['delivery_mode'])->toBe('online');
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
        ->and(Source::query()->count())->toBe(9);
});

it('does not ask when no external system is configured', function (): void {
    config(['migrations.base_url' => null]);
    Http::preventStrayRequests();

    // No expectsConfirmation: an unexpected prompt would fail the assertion
    // below, since the command would block on input it never receives.
    test()->artisan('db:seed', ['--class' => QualificaCatalogSeeder::class])->assertSuccessful();

    expect(MassMigrationRun::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(262);
});
