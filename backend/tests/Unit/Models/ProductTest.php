<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-013 — schema
// ---------------------------------------------------------------------------

it('creates the products table with the expected columns', function () {
    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasColumns('products', ['id', 'name', 'description', 'cost', 'price', 'category_id']))->toBeTrue();
});

it('a category with products cannot be deleted at the database level (restrictOnDelete)', function () {
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    expect(fn () => DB::table('product_categories')->where('id', $category->id)->delete())
        ->toThrow(QueryException::class);
});

it('down() reverses the products migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_08_100400_create_products_table.php');

    $migration->down();

    expect(Schema::hasTable('products'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('products'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-013 — model relations, cast, activity log, morph map
// ---------------------------------------------------------------------------

it('category() is a BelongsTo relation', function () {
    $product = new Product;

    expect($product->category())->toBeInstanceOf(BelongsTo::class);
});

it('logs model activity', function () {
    expect(class_uses(Product::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "product" morph alias', function () {
    expect(array_search(Product::class, Relation::morphMap(), true))->toBe('product');
});
