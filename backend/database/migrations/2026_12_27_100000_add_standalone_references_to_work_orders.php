<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0431 (D-4/D-7): a project commessa can be born without any offer. It
 * then carries its own client, company, sites and business-function lines,
 * which every other commessa keeps reading from its offer. `is_standalone`
 * tells it apart from a multi-offer commessa (also `quote_id` NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->boolean('is_standalone')->default(false)->after('quote_id');
            $table->foreignId('registry_id')->nullable()->after('is_standalone')->constrained('registries')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->after('registry_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_site_id')->nullable()->after('company_id')->constrained('company_sites')->restrictOnDelete();
            $table->foreignId('operational_site_id')->nullable()->after('company_site_id')->constrained('operational_sites')->restrictOnDelete();
        });

        Schema::create('work_order_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['work_order_id', 'business_function_id', 'product_category_id'], 'work_order_product_lines_unique_pair');
            $table->index('business_function_id');
            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_product_lines');

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operational_site_id');
            $table->dropConstrainedForeignId('company_site_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('registry_id');
            $table->dropColumn('is_standalone');
        });
    }
};
