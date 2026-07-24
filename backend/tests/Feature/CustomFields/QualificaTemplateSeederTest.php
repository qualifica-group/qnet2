<?php

use App\Models\CustomFieldDefinition;
use App\Models\Product;
use App\Models\ProductCategory;
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
        'Catalogo GOL', 'Autoimpiego', 'Yisu', 'Catalogo Autofinanziato',
        'Catalogo DIL', 'Servizi Consulenza',
    ];
    foreach ($expectedProducts as $name) {
        expect(Product::query()->where('name', $name)->count())->toBe(1);
    }

    $trattative = ProductCategory::query()->where('name', 'Trattative in Corso')->first();
    expect(Product::query()->where('name', 'Servizi Consulenza')->where('category_id', $trattative->id)->exists())->toBeTrue();
});
