<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0290 D-1: contract type catalogue (Tempo determinato, Apprendistato...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
