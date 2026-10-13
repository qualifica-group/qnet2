<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk launch of a settlement period (spec 0213, D-6): keeps the filters used
 * and the skipped (referent, company) pairs with their reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlement_runs', function (Blueprint $table) {
            $table->id();
            $table->date('period_from');
            $table->date('period_to');
            $table->json('filters')->nullable();
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->unsignedInteger('settlements_count')->default(0);
            $table->json('skipped')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('period_to', 'commission_settlement_runs_period_to_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlement_runs');
    }
};
