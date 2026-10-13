<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dated fiscal profile of an agent referent (spec 0212): withholding (ritenuta)
 * and ENASARCO settings valid from a date, so accruals use the profile in force.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_fiscal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referent_id')->constrained('referents')->restrictOnDelete();
            $table->boolean('withholding_enabled')->default(true);
            $table->decimal('withholding_rate', 5, 2)->nullable();
            $table->decimal('withholding_base_percent', 5, 2)->nullable();
            $table->boolean('enasarco_enabled')->default(false);
            $table->string('enasarco_mandate_type', 16)->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['referent_id', 'valid_from'], 'agent_fiscal_profiles_referent_valid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_fiscal_profiles');
    }
};
