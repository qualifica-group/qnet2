<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of accounts, phase 2 (spec 0350, D-5): the revenue sub-account of an
 * invoice line, and the default one of a product and of a product category.
 * The foreign key carries the index. At most one sub-account is flagged as the
 * default for the institution commissions (enforced by RevenueTypeService).
 */
return new class extends Migration
{
    private const array TABLES = ['invoice_lines', 'products', 'product_categories'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('revenue_type_id')->nullable()->constrained('revenue_types')->restrictOnDelete();
            });
        }

        Schema::table('revenue_types', function (Blueprint $table) {
            $table->boolean('is_institution_commission_default')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('revenue_types', function (Blueprint $table) {
            $table->dropColumn('is_institution_commission_default');
        });

        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('revenue_type_id');
            });
        }
    }
};
