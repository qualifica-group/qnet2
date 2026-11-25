<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a legacy attribute to the current one it continues (spec 0429, D-1):
 * the grids show both in one column. The initial links are written by the
 * one-shot `QualificaLinkedAttributesSeeder` (D-6), never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->foreignId('current_attribute_id')->nullable()->after('relation_target')->constrained('attributes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_attribute_id');
        });
    }
};
