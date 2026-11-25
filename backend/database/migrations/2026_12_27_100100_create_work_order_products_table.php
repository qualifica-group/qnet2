<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0431 (D-17): the products a project work order born without an offer
 * is about, in place of the offer lines it does not have. Their categories
 * bring the flexible fields ("Informazioni aggiuntive"), as the offer lines'
 * products do on any other work order. No price: such a work order is not billed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['work_order_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_products');
    }
};
