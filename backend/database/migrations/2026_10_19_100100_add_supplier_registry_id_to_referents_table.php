<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier registry an agent referent is paid through (spec 0267, D-7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referents', function (Blueprint $table) {
            $table->foreignId('supplier_registry_id')->nullable()->constrained('registries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_registry_id');
        });
    }
};
