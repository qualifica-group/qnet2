<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0094: "Prodotti di interesse" for the Lead, mirroring
 * `opportunity_product` (the same concept already in place for the
 * Opportunity). A pure many-to-many reference — no quantity, price or note.
 *
 * `lead_id` cascades (a deleted lead drops its own rows); `product_id` is
 * restrictOnDelete, mirroring every other product FK on this codebase (a
 * product referenced by a lead cannot silently vanish).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['lead_id', 'product_id'], 'lead_product_unique_pair');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_product');
    }
};
