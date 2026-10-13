<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nature and badge color of a cost type (spec 0249, D-1/D-3/D-4): values of
 * App\Enums\CostTypeNature and of App\Support\BadgeTokens. The column defaults
 * backfill the existing rows (nature direct, color slate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_types', function (Blueprint $table) {
            $table->string('nature', 16)->default('direct')->after('description');
            $table->string('color', 32)->default('slate')->after('nature');
        });
    }

    public function down(): void
    {
        Schema::table('cost_types', function (Blueprint $table) {
            $table->dropColumn(['nature', 'color']);
        });
    }
};
