<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cost type as the classification of every cost (spec 0251, ADR 0018): the
 * default type of a product and the type of a commessa's actual cost. Both are
 * optional and restrict the deletion of the cost type. No category-to-type
 * mapping is wired in (D-1): products start empty and are assigned in bulk.
 */
return new class extends Migration
{
    private const int BACKFILL_CHUNK = 500;

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('cost_type_id')->nullable()->constrained('cost_types')->restrictOnDelete();
        });

        Schema::table('work_order_costs', function (Blueprint $table) {
            $table->foreignId('cost_type_id')->nullable()->constrained('cost_types')->restrictOnDelete();
        });

        $this->backfill();
    }

    /**
     * Existing actual costs take the cost type of their product (D-3). Public
     * so the behaviour can be exercised on its own.
     */
    public function backfill(): void
    {
        $productIdsByType = DB::table('products')
            ->whereNotNull('cost_type_id')
            ->get(['id', 'cost_type_id'])
            ->groupBy('cost_type_id');

        foreach ($productIdsByType as $costTypeId => $products) {
            foreach ($products->pluck('id')->chunk(self::BACKFILL_CHUNK) as $productIds) {
                DB::table('work_order_costs')
                    ->whereIn('product_id', $productIds->all())
                    ->update(['cost_type_id' => $costTypeId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('work_order_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_type_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_type_id');
        });
    }
};
