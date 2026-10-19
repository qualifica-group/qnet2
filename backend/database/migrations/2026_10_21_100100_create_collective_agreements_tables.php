<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0272 D-3: collective labour agreement (CCNL) catalogue and its levels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collective_agreements', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('code', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('collective_agreement_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collective_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('description', 191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['collective_agreement_id', 'code'], 'ca_levels_agreement_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collective_agreement_levels');
        Schema::dropIfExists('collective_agreements');
    }
};
