<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the purchase order <-> sector relation (spec 0210, "Settore EA").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_sector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();

            $table->unique(['purchase_order_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_sector');
    }
};
