<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0320 AC-001. No RefreshDatabase on purpose: dropping the constrained
 * `products.supplier_id` makes SQLite rebuild the table, so the foreign-key
 * pragma (a no-op inside a transaction) must be effective, otherwise the
 * rebuild would cascade-delete the copied `product_suppliers` rows. A minimal
 * copy of the pre-0320 tables is built in the in-memory database and the real
 * migration files are run against it.
 */
function productSupplierMigration(string $file): Migration
{
    return require database_path('migrations/'.$file);
}

function createSuppliersMigration(): Migration
{
    return productSupplierMigration('2026_11_05_100000_create_product_suppliers_table.php');
}

function moveSupplierMigration(): Migration
{
    return productSupplierMigration('2026_11_05_100100_move_product_supplier_to_product_suppliers.php');
}

function preSpec0320Schema(): void
{
    Schema::create('registries', fn (Blueprint $table) => $table->id());
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->decimal('cost', 15, 2)->nullable();
        $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
    });
    Schema::create('quote_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->nullable()->constrained('products');
    });
    Schema::create('role_field_permissions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('role_id');
        $table->string('resource');
        $table->string('field');
        $table->unique(['role_id', 'resource', 'field']);
    });
    createSuppliersMigration()->up();
}

it('AC-001: the product supplier becomes the default row with the standard cost, null cost becomes 0', function () {
    preSpec0320Schema();
    DB::table('registries')->insert([['id' => 7]]);
    DB::table('products')->insert([
        ['id' => 1, 'cost' => 10, 'supplier_id' => 7],
        ['id' => 2, 'cost' => null, 'supplier_id' => 7],
        ['id' => 3, 'cost' => 5, 'supplier_id' => null],
    ]);

    moveSupplierMigration()->up();

    $rows = DB::table('product_suppliers')->orderBy('product_id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchObject(['product_id' => 1, 'supplier_id' => 7, 'is_default' => 1])
        ->and((float) $rows[0]->cost)->toBe(10.0)
        ->and($rows[1])->toMatchObject(['product_id' => 2, 'supplier_id' => 7, 'is_default' => 1])
        ->and((float) $rows[1]->cost)->toBe(0.0)
        ->and(Schema::hasColumn('products', 'supplier_id'))->toBeFalse()
        ->and((float) DB::table('products')->where('id', 1)->value('cost'))->toBe(10.0)
        ->and(DB::table('products')->where('id', 2)->value('cost'))->toBeNull()
        ->and((float) DB::table('products')->where('id', 3)->value('cost'))->toBe(5.0);
});

it('AC-001: quote lines take the supplier of their product', function () {
    preSpec0320Schema();
    DB::table('registries')->insert([['id' => 7]]);
    DB::table('products')->insert([['id' => 1, 'supplier_id' => 7], ['id' => 2, 'supplier_id' => null]]);
    DB::table('quote_lines')->insert([['id' => 1, 'product_id' => 1], ['id' => 2, 'product_id' => 2]]);

    moveSupplierMigration()->up();

    expect(DB::table('quote_lines')->where('id', 1)->value('supplier_id'))->toBe(7)
        ->and(DB::table('quote_lines')->where('id', 2)->value('supplier_id'))->toBeNull();
});

it('AC-001: the supplier field permissions are renamed to suppliers, dropping a conflicting row', function () {
    preSpec0320Schema();
    DB::table('role_field_permissions')->insert([
        ['role_id' => 1, 'resource' => 'products', 'field' => 'supplier_id'],
        ['role_id' => 2, 'resource' => 'products', 'field' => 'supplier_id'],
        ['role_id' => 2, 'resource' => 'products', 'field' => 'suppliers'],
        ['role_id' => 1, 'resource' => 'quotes', 'field' => 'supplier_id'],
    ]);

    moveSupplierMigration()->up();

    expect(DB::table('role_field_permissions')->where('resource', 'products')->orderBy('role_id')->pluck('field', 'role_id')->all())
        ->toBe([1 => 'suppliers', 2 => 'suppliers'])
        ->and(DB::table('role_field_permissions')->where('resource', 'quotes')->value('field'))->toBe('supplier_id');
});

it('AC-001: rollback restores products.supplier_id from the default supplier and drops the line column', function () {
    preSpec0320Schema();
    DB::table('registries')->insert([['id' => 7], ['id' => 8]]);
    DB::table('products')->insert([['id' => 1, 'cost' => 4, 'supplier_id' => 7], ['id' => 2, 'cost' => 6, 'supplier_id' => null]]);
    $move = moveSupplierMigration();
    $move->up();
    DB::table('product_suppliers')->insert([
        'product_id' => 1, 'supplier_id' => 8, 'cost' => 9, 'is_default' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $move->down();

    expect(Schema::hasColumn('products', 'supplier_id'))->toBeTrue()
        ->and(Schema::hasColumn('quote_lines', 'supplier_id'))->toBeFalse()
        ->and(DB::table('products')->where('id', 1)->value('supplier_id'))->toBe(7)
        ->and(DB::table('products')->where('id', 2)->value('supplier_id'))->toBeNull()
        ->and(DB::table('product_suppliers')->count())->toBe(2);

    createSuppliersMigration()->down();
    expect(Schema::hasTable('product_suppliers'))->toBeFalse();
});

it('keeps a supplier unique per product', function () {
    preSpec0320Schema();
    DB::table('registries')->insert([['id' => 7]]);
    DB::table('products')->insert([['id' => 1]]);
    $row = ['product_id' => 1, 'supplier_id' => 7, 'cost' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()];
    DB::table('product_suppliers')->insert($row);

    expect(fn () => DB::table('product_suppliers')->insert($row))->toThrow(QueryException::class);
});
