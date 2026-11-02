<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy `payments` fields qnet was missing (spec 0325, D-1): the FatturaPA
 * payment condition and the two months whose due dates are postponed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('payment_condition', 4)->default('TP02')->after('payment_method_code');
            $table->unsignedTinyInteger('excluded_month_1')->nullable()->after('end_of_month_extra_days');
            $table->unsignedTinyInteger('excluded_month_2')->nullable()->after('excluded_month_1');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['payment_condition', 'excluded_month_1', 'excluded_month_2']);
        });
    }
};
