<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0272 D-6: payment account. `iban` is text because the encrypted
 * payload is much longer than the IBAN itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->text('iban')->nullable();
            $table->string('iban_holder', 191)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropColumn(['iban', 'iban_holder']);
        });
    }
};
