<?php

use App\Migrations\Sources\ProductSuppliersSource;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * `product-suppliers` source (spec 0203, D-7; spec 0320, D-12): AC-012.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyProductSuppliersImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'product-suppliers']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function legacyServiceProduct(int $legacyId): Product
{
    return Product::factory()->create(['old_source' => 'services', 'old_id' => $legacyId]);
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-012: creates the default supplier row of a migrated product with the legacy cost', function () {
    $registry = Registry::factory()->create(['old_id' => 15269]);
    $product = legacyServiceProduct(5);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'name' => 'Corso', 'supplier_id' => 15269, 'cost' => 120.5]]);

    $row = $product->productSuppliers()->sole();

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)->and($run->report)->toBeNull()
        ->and($row->supplier_id)->toBe($registry->id)
        ->and((float) $row->cost)->toBe(120.5)
        ->and($row->is_default)->toBeTrue();
});

it('AC-024: the default supplier row also carries the legacy price, 0 when absent', function () {
    Registry::factory()->create(['old_id' => 15269]);
    $priced = legacyServiceProduct(5);
    $unpriced = legacyServiceProduct(6);

    runLegacyProductSuppliersImport([
        ['id' => 5, 'supplier_id' => 15269, 'price' => 300.25, 'cost' => 120.5],
        ['id' => 6, 'supplier_id' => 15269, 'cost' => 10],
    ]);

    $row = $priced->productSuppliers()->sole();

    expect((float) $row->price)->toBe(300.25)->and((float) $row->cost)->toBe(120.5)
        ->and((float) $unpriced->productSuppliers()->sole()->price)->toBe(0.0);
});

it('AC-012: a legacy record without cost gets a zero cost', function () {
    Registry::factory()->create(['old_id' => 15269]);
    $product = legacyServiceProduct(5);

    runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 15269, 'cost' => null]]);

    expect((float) $product->productSuppliers()->sole()->cost)->toBe(0.0);
});

it('AC-012: a product that already has suppliers is left untouched', function () {
    $chosen = Registry::factory()->create();
    Registry::factory()->create(['old_id' => 15269]);
    $product = legacyServiceProduct(5);
    ProductSupplier::factory()->default()->create(['product_id' => $product->id, 'supplier_id' => $chosen->id, 'cost' => 9]);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 15269, 'cost' => 50]]);

    $row = $product->productSuppliers()->sole();

    expect($run->skipped_rows)->toBe(1)->and($run->created_rows)->toBe(0)
        ->and($row->supplier_id)->toBe($chosen->id)->and((float) $row->cost)->toBe(9.0);
});

it('AC-012: a re-run is idempotent', function () {
    Registry::factory()->create(['old_id' => 15269]);
    $product = legacyServiceProduct(5);
    $records = [['id' => 5, 'supplier_id' => 15269, 'cost' => 10]];

    runLegacyProductSuppliersImport($records);
    $second = runLegacyProductSuppliersImport($records);

    expect($second->created_rows)->toBe(0)->and($second->skipped_rows)->toBe(1)
        ->and($product->productSuppliers()->count())->toBe(1);
});

it('AC-012: a supplier not migrated yet leaves the product without suppliers with a warning', function () {
    $product = legacyServiceProduct(5);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 777, 'cost' => 10]]);

    expect($run->failed_rows)->toBe(0)
        ->and($product->productSuppliers()->count())->toBe(0)
        ->and($run->report[0]['level'])->toBe('warning')
        ->and($run->report[0]['message'])->toContain('Supplier not migrated');
});

it('AC-012: a legacy product without supplier is skipped, an unmigrated product fails its row', function () {
    legacyServiceProduct(5);

    $run = runLegacyProductSuppliersImport([
        ['id' => 5, 'supplier_id' => null],
        ['id' => 6, 'supplier_id' => 15269],
    ]);

    expect($run->skipped_rows)->toBe(1)->and($run->failed_rows)->toBe(1)
        ->and($run->report[0]['message'])->toContain('Product not migrated');
});

it('AC-012: a cost product sharing the legacy id is never matched (old_source is services)', function () {
    Registry::factory()->create(['old_id' => 15269]);
    $cost = Product::factory()->create(['old_source' => 'costs', 'old_id' => 5]);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 15269, 'cost' => 3]]);

    expect($run->failed_rows)->toBe(1)->and($cost->productSuppliers()->count())->toBe(0);
});

it('AC-007: the source previews its columns', function () {
    $columns = collect(app(ProductSuppliersSource::class)->columns())->pluck('id')->all();

    expect($columns)->toBe(['id', 'name', 'supplier_id', 'price', 'cost']);
});
