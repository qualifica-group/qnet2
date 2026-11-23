<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0425, D-1: a Commessa field assignment can be synced with the offer —
 * one value shared by the offer and its work orders. Meaningful on the
 * `work_order` rows only; false everywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_category', function (Blueprint $table) {
            $table->boolean('syncs_with_quote')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_category', function (Blueprint $table) {
            $table->dropColumn('syncs_with_quote');
        });
    }
};
