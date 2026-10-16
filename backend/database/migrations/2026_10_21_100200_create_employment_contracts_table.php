<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0272 D-1: append-only history of a person's contractual conditions.
 * Company and CCNL level are RESTRICT: a referenced catalogue row cannot vanish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employment_profile_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->string('relationship_type');
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('collective_agreement_level_id')->nullable()
                ->constrained('collective_agreement_levels', indexName: 'employment_contracts_level_foreign')
                ->restrictOnDelete();
            $table->decimal('salary_amount', 12, 2)->nullable();
            $table->string('salary_period')->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['employment_profile_id', 'starts_on'], 'employment_contracts_profile_start_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
