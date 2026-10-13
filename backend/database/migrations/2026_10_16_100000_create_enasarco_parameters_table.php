<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENASARCO parameters per year (spec 0213, D-3): rates and ceilings are data,
 * never code constants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enasarco_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->decimal('total_rate', 5, 2);
            $table->decimal('agent_share_percent', 5, 2);
            $table->decimal('single_firm_ceiling', 15, 2);
            $table->decimal('multi_firm_ceiling', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enasarco_parameters');
    }
};
