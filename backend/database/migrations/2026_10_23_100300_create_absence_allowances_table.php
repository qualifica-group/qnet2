<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0278 D-1: yearly allowance (minutes) per person and balance-deducting type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_allowances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('absence_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('annual_minutes')->default(0);
            $table->integer('carried_over_minutes')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'absence_type_id', 'year'], 'absence_allowances_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_allowances');
    }
};
