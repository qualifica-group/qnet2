<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-supplier products (spec 0320, D-3/D-11): `products.supplier_id` becomes
 * the default `product_suppliers` row (cost = products.cost, 0 when null;
 * products.cost stays as the standard cost), every quote line takes the
 * supplier of its product, the field-permission rows of `supplier_id` become
 * `suppliers`, then the product column goes. down() restores the column from
 * the default supplier. The products table change runs without FK checks:
 * SQLite rebuilds the table and the DROP of the old copy would otherwise
 * cascade onto its children.
 */
return new class extends Migration
{
    private const RESOURCE = 'products';

    public function up(): void
    {
        $now = now();

        DB::table('product_suppliers')->insertUsing(
            ['product_id', 'supplier_id', 'cost', 'is_default', 'created_at', 'updated_at'],
            DB::table('products')
                ->whereNotNull('supplier_id')
                ->selectRaw('id, supplier_id, COALESCE(cost, 0), ?, ?, ?', [true, $now, $now]),
        );

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained('registries')->nullOnDelete();
        });

        DB::statement('UPDATE quote_lines SET supplier_id = (SELECT products.supplier_id FROM products WHERE products.id = quote_lines.product_id)');

        $this->renameField('supplier_id', 'suppliers');

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        }));
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
        }));

        DB::statement('UPDATE products SET supplier_id = (SELECT ps.supplier_id FROM product_suppliers ps WHERE ps.product_id = products.id ORDER BY ps.is_default DESC, ps.id LIMIT 1)');

        $this->renameField('suppliers', 'supplier_id');

        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }

    /**
     * Conflicting target rows are removed first (unique role/resource/field);
     * the role ids are read beforehand because MySQL forbids a DELETE whose
     * subquery selects from the table being deleted from.
     */
    private function renameField(string $from, string $to): void
    {
        $roleIds = DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $from)
            ->pluck('role_id')
            ->all();

        if ($roleIds !== []) {
            DB::table('role_field_permissions')
                ->where('resource', self::RESOURCE)
                ->where('field', $to)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $from)
            ->update(['field' => $to]);
    }
};
