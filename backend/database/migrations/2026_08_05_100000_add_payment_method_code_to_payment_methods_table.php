<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The payment modality's own fiscal/legacy code (e.g. the electronic-invoice
 * "MP01" family), distinct from `code` — qnet's unique snake_case identity.
 * Deliberately NOT unique and NOT formatted: it is a classification many
 * methods legitimately share ("MP01" covers every cash-equivalent modality in
 * the legacy system), so it is indexed for filtering/search only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('payment_method_code', 32)->nullable()->after('code');
            $table->index('payment_method_code');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['payment_method_code']);
            $table->dropColumn('payment_method_code');
        });
    }
};
