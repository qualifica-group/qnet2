<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment method lookup entity (spec 0068): a standalone, consumer-agnostic
 * lookup describing the payment modalities selectable across the CRM
 * (quotes, offers, contracts, orders, invoices and future modules). No
 * system row (unlike its `reward_statuses` template, D-1): every row is a
 * plain, user-owned custom row, resequenced by a dedicated resequencer with
 * no head/tail concept (App\Services\PaymentMethods\PaymentMethodOrderManager).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('code', 64)->unique();
            $table->string('description', 500)->nullable();
            $table->text('payment_instructions')->nullable();
            $table->unsignedSmallInteger('payment_days')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
