<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0278 D-5: absence type catalogue (Ferie, Permesso, Malattia...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('deducts_balance');
            $table->boolean('requires_approval');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_types');
    }
};
