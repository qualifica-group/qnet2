<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Account chosen when an active installment is collected (spec 0355, D-5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_account_id');
        });
    }
};
