<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the employment-profile <-> product-category competence (spec
 * 0110): the product categories a user is operative on, paired with the
 * business function already carried by `employment_profiles`, to decide who
 * may receive a record in assignment (INV-3).
 *
 * The pivot hangs off `employment_profiles`, not `users`, for the same
 * reason the site membership does (spec 0103): the competence is part of the
 * employment relationship, so deleting the profile drops it with the row.
 *
 * Both sides cascade, mirroring `employment_profile_operational_site`. The
 * category side does NOT restrict on delete the way `products.category_id`
 * does: a competence is a per-user preference, never a business record worth
 * blocking a category's deletion for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_profile_product_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_profile_id');
            $table->foreignId('product_category_id');

            // Named explicitly: the table name alone already pushes
            // Laravel's default `<table>_<column>_foreign` past MySQL's
            // 64-character identifier limit.
            $table->foreign('employment_profile_id', 'ep_prod_cat_employment_fk')
                ->references('id')->on('employment_profiles')->cascadeOnDelete();
            $table->foreign('product_category_id', 'ep_prod_cat_category_fk')
                ->references('id')->on('product_categories')->cascadeOnDelete();

            $table->unique(
                ['employment_profile_id', 'product_category_id'],
                'ep_prod_cat_unique',
            );
            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_profile_product_category');
    }
};
