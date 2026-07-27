<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Source;
use App\Services\ProductCategoryService;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// De-verticalization (point 1): QualificaTemplateSeeder now provisions, on
// top of the former "Altro" section, the 9 former client-specific ERP
// settings columns (responsible_*, proforma/invoice progressives,
// quotation_*) as company-sites custom fields.
uses(RefreshDatabase::class);

it('provisions the 9 de-verticalized ERP fields for company-sites, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: updateOrCreate, no duplicates.

    $definitions = CustomFieldDefinition::query()
        ->where('entity_type', 'company-sites')
        ->get()
        ->keyBy('key');

    expect($definitions)->toHaveCount(36);

    $relations = [
        'responsible_rda', 'responsible_tickets',
        'responsible_validation_contracts', 'responsible_validation_contracts_two',
    ];

    foreach ($relations as $key) {
        expect($definitions[$key]->type)->toBe('relation')
            ->and($definitions[$key]->relation_target)->toBe([
                'entity_type' => 'users',
                'cardinality' => 'one',
                'for_select_resource' => 'users',
            ])
            ->and($definitions[$key]->is_active)->toBeTrue();
    }

    foreach (['proforma_progressive', 'invoice_progressive', 'quotation_layout', 'quotation_header', 'quotation_footer'] as $key) {
        expect($definitions[$key]->type)->toBe('integer')
            ->and($definitions[$key]->is_active)->toBeTrue();
    }
});

it('provisions the product template: months of validity plus the folder enum, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: updateOrCreate, no duplicates.

    $definitions = CustomFieldDefinition::query()
        ->where('entity_type', 'products')
        ->get()
        ->keyBy('key');

    expect($definitions)->toHaveCount(2)
        ->and($definitions['expiration_months']->type)->toBe('integer')
        ->and($definitions['expiration_months']->label)->toBe('Mesi scadenza')
        ->and($definitions['folder']->type)->toBe('enum')
        ->and($definitions['folder']->label)->toBe('Cartella');

    $options = $definitions['folder']->options()->get();

    expect($options->pluck('value')->all())->toBe(['ente', 'consulenza'])
        ->and($options->pluck('label')->all())->toBe(['Ente', 'Consulenza']);
});

it('prunes the superseded product expiration date, definition and stored values', function (): void {
    $superseded = CustomFieldDefinition::factory()->create([
        'entity_type' => 'products',
        'key' => 'expiration_date',
        'type' => 'date',
        'label' => 'Data scadenza',
    ]);

    CustomFieldOption::factory()->create(['definition_id' => $superseded->id]);

    $values = CustomFieldValue::factory()->create([
        'entity_type' => 'products',
        'entity_id' => 1,
        'values' => ['expiration_date' => '2025-04-30', 'expiration_months' => 24],
    ]);

    test()->seed(QualificaTemplateSeeder::class);

    expect(CustomFieldDefinition::query()->where('entity_type', 'products')->where('key', 'expiration_date')->exists())->toBeFalse()
        ->and(CustomFieldOption::query()->where('definition_id', $superseded->id)->exists())->toBeFalse()
        ->and($values->fresh()->values)->toBe(['expiration_months' => 24]);
});

it('provisions the client source catalogue, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: firstOrCreate, no duplicates.

    $expected = [
        'Diretto', 'Passaparola', 'Social', 'Sito', 'Spoki',
        'Centralino', 'In Sede', 'Segnalatore', 'Spontaneo',
    ];

    expect(Source::query()->whereIn('name', $expected)->count())->toBe(count($expected));
    expect(Source::query()->count())->toBe(count($expected));
});

it('provisions the client reward type catalogue, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: firstOrCreate, no duplicates.

    expect(RewardType::query()->where('name', 'Buono Amazon')->count())->toBe(1)
        ->and(RewardType::query()->where('name', 'Buono Amazon')->value('color'))->toBe('orange');
});

it('provisions the reference category catalogue tree, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: firstOrCreate, no duplicates.

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
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: firstOrCreate, no duplicates.

    $gol = ProductCategory::query()->where('name', 'GOL')->first();
    expect($gol)->not->toBeNull();

    $regions = [
        'GOL - Molise', 'GOL - Abruzzo', 'GOL - Calabria', 'GOL - Campania',
        'GOL - Lombardia', 'GOL - Lazio', 'GOL - Umbria',
    ];

    foreach ($regions as $name) {
        expect(ProductCategory::query()->where('name', $name)->where('parent_id', $gol->id)->count())->toBe(1);
    }

    expect(ProductCategory::query()->where('parent_id', $gol->id)->count())->toBe(count($regions));
});

it('assigns the "Ore complessive" product attribute to the whole Formazione branch', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: no duplicate attribute nor pivot row.

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

it('seeds no product at all: the catalogue is categories only', function (): void {
    test()->seed(QualificaTemplateSeeder::class);

    expect(Product::query()->count())->toBe(0);
});
