<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Financial sub-accounts of the chart of accounts (spec 0360, D-3): same shape as `revenue_types`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_types');
    }
};
