<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-position denomination override for the "Gestore Account" levels (spec
 * 0080): `manager_labels` is a sparse position->label map (keys "1".."4",
 * ProductCategory::MANAGER_LABEL_MAX_POSITION), only the CONFIGURED
 * positions present — null/{} means "no own labels". `inherits_manager_labels`
 * is the per-context inheritance barrier, same shape as
 * `inherits_product_attributes`/`inherits_opportunity_attributes` (spec
 * 0061): a category climbs its ancestors for the labels it does not define
 * itself unless this flag is false.
 *
 * Neither column changes the GA structure itself (opportunity_user/
 * registry_user, manager_slots, OPERATOR_MANAGER_POSITION) — purely an
 * additive denomination layer resolved read-side
 * (CategoryManagerLabelResolver / OpportunityManagerLabelResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->json('manager_labels')->nullable()->after('management_mode');
            $table->boolean('inherits_manager_labels')->default(true)->after('manager_labels');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['manager_labels', 'inherits_manager_labels']);
        });
    }
};
