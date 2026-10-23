<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity of a tracked product in a site, free or assigned to a user
 * (spec 0307, D-4/D-13). `holder_key` is a STORED generated column that maps
 * a NULL user to 0: MySQL treats two NULLs as distinct in a unique index, so
 * the uniqueness of the free row of a (product, site) pair needs a non-null
 * key. Rows at zero are deleted by the service, never kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('operational_site_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('holder_key')->storedAs('COALESCE(user_id, 0)');
            $table->decimal('quantity', 12, 3);
            $table->date('last_moved_on');
            $table->timestamps();

            $table->unique(['product_id', 'operational_site_id', 'holder_key'], 'stock_balances_holder_unique');
            $table->index('operational_site_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
