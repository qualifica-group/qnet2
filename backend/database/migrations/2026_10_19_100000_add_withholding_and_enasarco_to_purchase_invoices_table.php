<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax and ENASARCO borne by the payee on a purchase invoice (spec
 * 0267, D-6). Both reduce the amount to pay; existing documents get 0.00.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->decimal('withholding_amount', 15, 2)->default(0)->after('vat_amount');
            $table->decimal('enasarco_amount', 15, 2)->default(0)->after('withholding_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['withholding_amount', 'enasarco_amount']);
        });
    }
};
