<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-node "Attiva" flag of a business function (spec 0280), same shape as
 * `sectors.is_active`. Default true: at deploy no function changes state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_functions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_functions', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
