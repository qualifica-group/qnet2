<?php

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\CustomFieldValue;
use App\Models\DocumentLayout;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Source;
use Database\Seeders\QualificaDocumentLayoutSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

// De-verticalization (point 1): QualificaTemplateSeeder now provisions, on
// top of the former "Altro" section, the 9 former client-specific ERP
// settings columns (responsible_*, proforma/invoice progressives,
// quotation_*) as company-sites custom fields.
//
// The client's reference DATA (sources, reward types, category tree, courses)
// is not here: it moved to QualificaCatalogSeeder, covered by
// tests/Feature/Products/QualificaCatalogSeederTest.php. Its document layout
// IS a step of this seeder, but only its presence is asserted here — the
// transcription itself is covered by
// tests/Feature/Seeding/QualificaDocumentLayoutSeederTest.php.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The layout step uploads the letterhead: without this, every run of this
    // suite would leave a real binary under storage/app.
    Storage::fake(config('attachments.disk'));
});

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

it('seeds no reference row: no source, category or product', function (): void {
    test()->seed(QualificaTemplateSeeder::class);

    // The client's reference data belongs to QualificaCatalogSeeder: this
    // seeder defines fields, and provisions the one layout below.
    expect(Source::query()->count())->toBe(0)
        ->and(ProductCategory::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0);
});

it('provisions the client document layout, with its letterhead attached', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaTemplateSeeder::class); // re-run: keyed on `code`, no duplicates.

    $layout = DocumentLayout::query()->where('code', QualificaDocumentLayoutSeeder::LAYOUT_CODE)->sole();

    expect($layout->module->value)->toBe('quotes')
        ->and($layout->images()->count())->toBe(1)
        ->and($layout->config['header']['blocks'][0]['attachment_id'])->toBe($layout->images()->sole()->id);
});
