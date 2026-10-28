<?php

use App\Models\Product;
use App\Models\Registry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Spec 0320 rev. 2, AC-018: the supplier rows already saved take the
 * product's price (0 without one); rollback drops the column again.
 */
function supplierPriceMigration(): Migration
{
    return require database_path('migrations/2026_11_05_100200_add_price_to_product_suppliers_table.php');
}

it('0320 AC-018: backfills price from the product price, 0 for a zero price, and rolls back', function () {
    $migration = supplierPriceMigration();
    $migration->down();

    $supplier = Registry::factory()->supplier()->create();
    $priced = Product::factory()->create(['price' => 120]);
    $unpriced = Product::factory()->create(['price' => 0]);
    DB::table('product_suppliers')->insert([
        ['product_id' => $priced->id, 'supplier_id' => $supplier->id, 'cost' => 80, 'is_default' => true],
        ['product_id' => $unpriced->id, 'supplier_id' => $supplier->id, 'cost' => 5, 'is_default' => true],
    ]);

    $migration->up();

    $prices = DB::table('product_suppliers')->pluck('price', 'product_id');
    expect((float) $prices[$priced->id])->toBe(120.0)
        ->and((float) $prices[$unpriced->id])->toBe(0.0)
        ->and((float) DB::table('product_suppliers')->where('product_id', $priced->id)->value('cost'))->toBe(80.0);

    $migration->down();
    expect(Schema::hasColumn('product_suppliers', 'price'))->toBeFalse();

    $migration->up();
});
