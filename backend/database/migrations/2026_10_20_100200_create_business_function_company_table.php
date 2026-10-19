<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 0271 D-4: a business function belongs to 0..n companies. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_function_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_function_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unique(['business_function_id', 'company_id'], 'business_function_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_function_company');
    }
};
