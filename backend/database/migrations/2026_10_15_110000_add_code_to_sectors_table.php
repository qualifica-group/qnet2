<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sector's "Codice" (spec 0213): a string kept verbatim (01, 07a, NA),
 * nullable because migrated legacy sectors carry none, unique among the
 * sectors that have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('old_id');
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
