<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Referente secondario" (Referente 2) of the anagrafica (spec 0408): one of its
 * linked referents other than the principale, or none. Same FK discipline as
 * primary_referent_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->foreignId('secondary_referent_id')->nullable()->after('primary_referent_id')->constrained('referents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secondary_referent_id');
        });
    }
};
