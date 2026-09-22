<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `quote_lines.offer_line_id` (spec 0144, D-2): a COST row's optional pointer
 * to the REVENUE row (of the SAME quote) it is imputed to — self-referential
 * FK on `quote_lines.id`. `nullOnDelete`: removing the referenced product
 * line (from any channel) must leave the cost row in place and simply fall
 * back to "generic cost" (D-3), never cascade its deletion. Nullable, no
 * backfill: every existing row reads as a generic cost. Valorized ONLY on
 * COST rows — enforced by `QuoteLineWriter::sync()`, never by this schema
 * (a REVENUE row could technically point at another REVENUE row, but no
 * writer ever sets it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table): void {
            $table->foreignId('offer_line_id')->nullable()
                ->after('total_amount')->constrained('quote_lines')->nullOnDelete();
            $table->index('offer_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table): void {
            $table->dropForeign(['offer_line_id']);
            $table->dropIndex(['offer_line_id']);
            $table->dropColumn('offer_line_id');
        });
    }
};
