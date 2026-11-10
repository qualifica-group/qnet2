<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote acceptance date (spec 0365, D-6): the day the quote entered a won status.
 * Written by QuoteAcceptedDateStamper, backfilled from the legacy by the
 * `quote-accepted-dates` migration source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->date('accepted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['accepted_at']);
            $table->dropColumn('accepted_at');
        });
    }
};
