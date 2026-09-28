<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0171, D-1: marks an opportunity title typed by the user, so the
 * automatic re-derivation from the quoted products (spec 0077) leaves it
 * alone. Existing rows stay automatic. Not in Opportunity::$fillable: written
 * only by OpportunityNameWriter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->boolean('name_is_manual')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn('name_is_manual');
        });
    }
};
