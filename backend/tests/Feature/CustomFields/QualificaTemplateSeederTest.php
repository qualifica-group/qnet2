<?php

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Source;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('provisions the reference product catalogue tree, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: firstOrCreate, no duplicates.

    $formazione = ProductCategory::query()->where('name', 'Formazione')->whereNull('parent_id')->first();
    $consulenza = ProductCategory::query()->where('name', 'Consulenza')->whereNull('parent_id')->first();

    expect($formazione)->not->toBeNull()
        ->and($consulenza)->not->toBeNull();

    $formazioneSubs = ['GOL', 'Autoimpiego', 'Yisu', 'Autofinanziato', 'DIL'];
    foreach ($formazioneSubs as $name) {
        expect(ProductCategory::query()->where('name', $name)->where('parent_id', $formazione->id)->exists())->toBeTrue();
    }

    foreach (['Trattative in Corso', 'Presa Appuntamenti'] as $name) {
        expect(ProductCategory::query()->where('name', $name)->where('parent_id', $consulenza->id)->exists())->toBeTrue();
    }

    // Presa Appuntamenti carries no product (empty list in CATALOG).
    $presaAppuntamenti = ProductCategory::query()->where('name', 'Presa Appuntamenti')->first();
    expect(Product::query()->where('category_id', $presaAppuntamenti->id)->count())->toBe(0);

    $expectedProducts = [
        'Prodotto per formazione GOL', 'Prodotto per formazione Autoimpiego',
        'Prodotto per formazione Yisu', 'Prodotto per formazione Autofinanziato',
        'Prodotto per formazione DIL', 'Prodotto per consulenza Trattative in Corso',
    ];
    foreach ($expectedProducts as $name) {
        expect(Product::query()->where('name', $name)->count())->toBe(1);
    }

    $trattative = ProductCategory::query()->where('name', 'Trattative in Corso')->first();
    expect(Product::query()->where('name', 'Prodotto per consulenza Trattative in Corso')->where('category_id', $trattative->id)->exists())->toBeTrue();
});
