<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installment configuration of a payment method (spec 0194, D-10). The existing
 * `payment_days` stays the days to the first due date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->unsignedTinyInteger('installments_count')->default(1)->after('payment_days');
            $table->unsignedSmallInteger('days_between_installments')->default(0)->after('installments_count');
            $table->boolean('end_of_month')->default(false)->after('days_between_installments');
            $table->unsignedTinyInteger('end_of_month_extra_days')->nullable()->after('end_of_month');
            $table->string('vat_allocation', 16)->default('split')->after('end_of_month_extra_days');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'installments_count', 'days_between_installments', 'end_of_month',
                'end_of_month_extra_days', 'vat_allocation',
            ]);
        });
    }
};
