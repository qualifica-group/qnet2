<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0094 (D-4): per-row override of the Lead's "Prodotti di interesse"
 * during import review, on the same model already used for Operator /
 * Operational Site (spec 0045): a dedicated column, never inside `values`
 * (which is constrained to string). `null` means "inherit the run's global
 * `product_ids`"; `[]` means "no products on this row".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table): void {
            $table->json('product_ids')->nullable()->after('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table): void {
            $table->dropColumn('product_ids');
        });
    }
};
