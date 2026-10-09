<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-node "Attivo" flag of a sector (spec 0212), same shape as
 * `product_categories.is_active`. Default true: at deploy no sector changes state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
