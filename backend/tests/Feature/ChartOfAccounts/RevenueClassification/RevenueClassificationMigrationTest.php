<?php

use App\Models\Product;
use App\Models\ProformaRequest;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function dataMigration(): object
{
    return require database_path('migrations/2026_11_19_100100_classify_institution_invoice_lines_revenue.php');
}

it('AC-001: the schema has the three columns and the flag', function () {
    foreach (['invoice_lines', 'products', 'product_categories'] as $table) {
        expect(Schema::hasColumn($table, 'revenue_type_id'))->toBeTrue();
    }

    expect(Schema::hasColumn('revenue_types', 'is_institution_commission_default'))->toBeTrue();
});

it('AC-001: the data migration flags 70.03.001 and classifies only the unclassified institution lines, and rolls back', function () {
    $seeded = RevenueType::query()->where('code', '70.03.001')->firstOrFail();
    $manual = revenueSubAccount();
    $seeded->update(['is_institution_commission_default' => false]);

    $product = Product::factory()->create();
    $ente = invoiceLineOf($product->id, true);
    $enteManual = invoiceLineOf($product->id, true, ['revenue_type_id' => $manual->id]);
    $sale = invoiceLineOf($product->id);

    dataMigration()->up();
    dataMigration()->up();

    expect($seeded->fresh()->is_institution_commission_default)->toBeTrue()
        ->and($ente->fresh()->revenue_type_id)->toBe($seeded->id)
        ->and($enteManual->fresh()->revenue_type_id)->toBe($manual->id)
        ->and($sale->fresh()->revenue_type_id)->toBeNull()
        ->and(ProformaRequest::query()->where('kind', 'institution')->count())->toBe(2);

    dataMigration()->down();

    expect($seeded->fresh()->is_institution_commission_default)->toBeFalse()
        ->and($ente->fresh()->revenue_type_id)->toBeNull()
        ->and($enteManual->fresh()->revenue_type_id)->toBe($manual->id);
});

it('AC-001: without 70.03.001 the data migration does nothing', function () {
    RevenueType::query()->update(['is_institution_commission_default' => false]);
    RevenueType::query()->where('code', '70.03.001')->update(['code' => '99.99.999']);
    $ente = invoiceLineOf(null, true);

    dataMigration()->up();

    expect($ente->fresh()->revenue_type_id)->toBeNull()
        ->and(DB::table('revenue_types')->where('is_institution_commission_default', true)->count())->toBe(0);
});

it('AC-001: the schema migration rolls back the columns and the flag', function () {
    $schema = require database_path('migrations/2026_11_19_100000_add_revenue_type_to_invoice_lines_products_and_categories.php');

    $schema->down();

    foreach (['invoice_lines', 'products', 'product_categories'] as $table) {
        expect(Schema::hasColumn($table, 'revenue_type_id'))->toBeFalse();
    }

    expect(Schema::hasColumn('revenue_types', 'is_institution_commission_default'))->toBeFalse();
});
