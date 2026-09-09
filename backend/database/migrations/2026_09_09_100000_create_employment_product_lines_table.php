<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0111 (D-1/D-3): a user's competence is a COLLECTION of "funzione
 * aziendale + categoria prodotto" rows, exactly like the ones an Opportunity
 * / Project / Campaign carries (`opportunity_product_lines`, spec 0040
 * amendment rev.3) — it SUBSTITUTES both the single
 * `employment_profiles.business_function_id` column (dropped by the next
 * migration) and the category-only pivot the previous revision of this file
 * created.
 *
 * The rows hang off `employment_profiles`, not `users`, for the same reason
 * the site membership does (spec 0103): the competence is part of the
 * employment relationship, so deleting the profile drops it with the row.
 * The two lookup FKs stay restrictOnDelete, mirroring every other product
 * line table (a function/category in use is never silently unlinked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_profile_id');
            $table->foreignId('business_function_id');
            $table->foreignId('product_category_id');
            $table->timestamps();

            // Named explicitly: the table name alone already pushes
            // Laravel's default `<table>_<column>_foreign` past MySQL's
            // 64-character identifier limit.
            $table->foreign('employment_profile_id', 'emp_prod_lines_profile_fk')
                ->references('id')->on('employment_profiles')->cascadeOnDelete();
            $table->foreign('business_function_id', 'emp_prod_lines_function_fk')
                ->references('id')->on('business_functions')->restrictOnDelete();
            $table->foreign('product_category_id', 'emp_prod_lines_category_fk')
                ->references('id')->on('product_categories')->restrictOnDelete();

            $table->unique(
                ['employment_profile_id', 'business_function_id', 'product_category_id'],
                'emp_prod_lines_unique',
            );
            $table->index('business_function_id', 'emp_prod_lines_function_idx');
            $table->index('product_category_id', 'emp_prod_lines_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_product_lines');
    }
};
