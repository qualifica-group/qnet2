<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per (company, year) counter for commission settlement numbers (spec 0213, D-1):
 * same scheme as invoice_number_sequences, a number is never reused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlement_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'year'], 'commission_settlement_sequences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlement_number_sequences');
    }
};
