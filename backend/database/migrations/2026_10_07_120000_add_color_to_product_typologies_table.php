<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Badge color of a product typology (spec 0204, D-3): a BadgeTokens token,
 * `gray` for every existing row, then the two reference typologies get their
 * own (`institution` -> violet, `consultancy` -> blue), resolved by code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->string('color', 32)->default('gray')->after('description');
        });

        DB::table('product_typologies')->where('code', 'institution')->update(['color' => 'violet']);
        DB::table('product_typologies')->where('code', 'consultancy')->update(['color' => 'blue']);
    }

    public function down(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
