<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0135: an operational site can be deactivated. Every existing site
 * stays active (default true); an inactive site keeps showing on the records
 * already linked to it but is no longer offered by the for-select pickers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_sites', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('alias');

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('operational_sites', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
