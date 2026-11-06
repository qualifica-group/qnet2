<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renewal history of a contract (spec 0358, D-2): one row per renewal period.
 * The period 0 (initial) has no row; the current period is MAX(number) or 0.
 * `previous_*` keep the dates the contract had before the renewal so that
 * undoing the latest renewal can restore them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('starts_on');
            $table->date('previous_renewal_date')->nullable();
            $table->date('previous_expiry_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->date('expiry_date');
            $table->foreignId('renewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_renewals');
    }
};
