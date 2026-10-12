<?php

use App\Migrations\Sources\ProductSuppliersSource;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * `product-suppliers` source (spec 0203, D-7): AC-006.
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

function legacyServiceProduct(int $legacyId, ?int $supplierId = null): Product
{
    return Product::factory()->create(['old_source' => 'services', 'old_id' => $legacyId, 'supplier_id' => $supplierId]);
}

beforeEach(fn () => seedMigrationsConfig());

it('AC-006: sets the supplier of a migrated product from the migrated registry', function () {
    $registry = Registry::factory()->create(['old_id' => 15269]);
    $product = legacyServiceProduct(5);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'name' => 'Corso', 'supplier_id' => 15269]]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0)->and($run->report)->toBeNull()
        ->and($product->fresh()->supplier_id)->toBe($registry->id);
});

it('AC-006: a product that already has a supplier is left untouched and a re-run changes nothing', function () {
    $chosen = Registry::factory()->create();
    Registry::factory()->create(['old_id' => 15269]);
    $product = legacyServiceProduct(5, $chosen->id);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 15269]]);

    expect($run->skipped_rows)->toBe(1)->and($run->created_rows)->toBe(0)
        ->and($product->fresh()->supplier_id)->toBe($chosen->id);
});

it('AC-006: a supplier not migrated yet leaves the product as is with a warning', function () {
    $product = legacyServiceProduct(5);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 777]]);

    expect($run->failed_rows)->toBe(0)
        ->and($product->fresh()->supplier_id)->toBeNull()
        ->and($run->report[0]['level'])->toBe('warning')
        ->and($run->report[0]['message'])->toContain('Supplier not migrated');
});

it('AC-006: a legacy product without supplier is skipped, an unmigrated product fails its row', function () {
    legacyServiceProduct(5);

    $run = runLegacyProductSuppliersImport([
        ['id' => 5, 'supplier_id' => null],
        ['id' => 6, 'supplier_id' => 15269],
    ]);

    expect($run->skipped_rows)->toBe(1)->and($run->failed_rows)->toBe(1)
        ->and($run->report[0]['message'])->toContain('Product not migrated');
});

it('AC-006: a cost product sharing the legacy id is never matched (old_source is services)', function () {
    Registry::factory()->create(['old_id' => 15269]);
    $cost = Product::factory()->create(['old_source' => 'costs', 'old_id' => 5]);

    $run = runLegacyProductSuppliersImport([['id' => 5, 'supplier_id' => 15269]]);

    expect($run->failed_rows)->toBe(1)->and($cost->fresh()->supplier_id)->toBeNull();
});

it('AC-007: the source previews its columns', function () {
    $columns = collect(app(ProductSuppliersSource::class)->columns())->pluck('id')->all();

    expect($columns)->toBe(['id', 'name', 'supplier_id']);
});
