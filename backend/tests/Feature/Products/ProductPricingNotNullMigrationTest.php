<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0320 rev. 3, AC-026: product price and cost become NOT NULL default 0
 * (NULL turned into 0); rollback makes them nullable again.
 */
function productPricingNotNullMigration(): Migration
{
    return require database_path('migrations/2026_11_05_100300_make_product_price_and_cost_not_nullable.php');
}

it('0320 AC-026: turns NULL price and cost into 0, defaults new rows to 0 and rolls back', function () {
    $migration = productPricingNotNullMigration();
    $migration->down();

    $product = Product::factory()->create(['price' => null, 'cost' => null]);

    $migration->up();

    $row = DB::table('products')->where('id', $product->id)->first(['price', 'cost']);
    expect((float) $row->price)->toBe(0.0)->and((float) $row->cost)->toBe(0.0);
    expect(fn () => DB::table('products')->where('id', $product->id)->update(['price' => null]))
        ->toThrow(QueryException::class);

    $migration->down();
    DB::table('products')->where('id', $product->id)->update(['price' => null, 'cost' => null]);
    expect(DB::table('products')->where('id', $product->id)->value('price'))->toBeNull();

    DB::table('products')->where('id', $product->id)->update(['price' => 0, 'cost' => 0]);
    $migration->up();
});
