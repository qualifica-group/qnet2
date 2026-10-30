<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Referente principale" of the anagrafica (spec 0330): one of its linked
 * referents, the default Referente when the anagrafica is picked on an
 * opportunity or a task. Same FK discipline as commercial_id/reporter_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->foreignId('primary_referent_id')->nullable()->after('reporter_id')->constrained('referents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_referent_id');
        });
    }
};
