<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line payment data of a commessa (spec 0201, D-11): one row per
 * programmed offer line, created on first save. `quote_line_id` is UNIQUE
 * because a line belongs to a single commessa. The status FK is RESTRICT:
 * a status in use is never silently blanked (the 409 guard in
 * WorkOrderPaymentStatusService is the informative half).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_line_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_line_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_payment_status_id')->nullable()->constrained('work_order_payment_statuses')->restrictOnDelete();
            $table->text('payment_agreement')->nullable();
            $table->boolean('has_unpaid')->default(false);
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_line_payments');
    }
};
