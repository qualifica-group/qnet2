<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `state_id` (Regione) to `products`: a nullable, user-editable
 * reference to the geo `states` table, mirroring the same FK on
 * Lead/Opportunity/Project/Campaign/Address. nullOnDelete — a product never
 * restricts removal of a Region.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->after('supplier_id')->constrained('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
        });
    }
};
