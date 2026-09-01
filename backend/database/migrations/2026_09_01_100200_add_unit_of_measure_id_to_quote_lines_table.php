<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `quote_lines.unit_of_measure_id` (spec 0088, D-5): NULLABLE, restrictOnDelete
 * — emends D-7 of spec 0065 (the line otherwise reads its product live)
 * limitedly to the unit of measure, which qualifies the already-frozen
 * `quantity` (10 Kg must never silently read as 10 Grammi after a later
 * product edit). Populated by QuoteLineWriter::sync() from the Product at
 * write time. Existing rows stay NULL (no backfill, unlike Products): a
 * historic line falls back to the product's CURRENT unit in QuoteLineResource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->foreignId('unit_of_measure_id')->nullable()->after('product_id')->constrained('units_of_measure')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropForeign(['unit_of_measure_id']);
            $table->dropColumn('unit_of_measure_id');
        });
    }
};
