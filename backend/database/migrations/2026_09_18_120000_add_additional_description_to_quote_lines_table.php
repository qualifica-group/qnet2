<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `quote_lines.additional_description`: free text the operator adds to one
 * line, on top of the product's own (live) description, printable in the
 * generated quote document through the products_table `additional_description`
 * column key. Nullable, no backfill: existing lines simply have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->text('additional_description')->nullable()->after('unit_of_measure_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('additional_description');
        });
    }
};
