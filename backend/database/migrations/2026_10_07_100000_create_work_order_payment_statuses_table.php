<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable payment-status catalogue of a commessa's lines (spec 0201,
 * D-2): the reward_statuses lookup shape WITHOUT `group`/`system_key`, plus
 * `allows_delivery` ("si puo' consegnare") and the nullable + unique `old_id`
 * the legacy import resolves the legacy `stato_pagamento` key through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_payment_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_id')->nullable()->unique();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_delivery')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_payment_statuses');
    }
};
