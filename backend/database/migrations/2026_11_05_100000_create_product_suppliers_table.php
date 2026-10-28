<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product suppliers (spec 0320, D-1): a product has 0..N suppliers, each with
 * its own cost, exactly one flagged default when the list is not empty.
 * Deleting a product removes its rows; a registry used as supplier cannot be
 * deleted (restrict, guarded in RegistryService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('registries')->restrictOnDelete();
            $table->decimal('cost', 15, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
