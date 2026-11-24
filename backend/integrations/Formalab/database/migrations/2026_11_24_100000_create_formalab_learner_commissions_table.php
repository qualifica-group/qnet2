<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial partner data of a learner (spec 0427 D-2), the qnet-2 home of the
 * legacy pre_info_formaziones partner_commerciale* columns: one row per tax code,
 * like the legacy prospetto that reads the first pre-info row of a tax code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalab_learner_commissions', function (Blueprint $table): void {
            $table->id();
            $table->string('tax_code', 32)->unique();
            $table->string('partner_name')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->foreignId('supplier_registry_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalab_learner_commissions');
    }
};
