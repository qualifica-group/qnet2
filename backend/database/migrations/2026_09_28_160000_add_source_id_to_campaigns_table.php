<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0176 (D-1): a campaign may name the Fonte its leads inherit.
 * Optional; restrict-on-delete like `leads.source_id` (SourceService::delete()
 * guards on it before deleting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreignId('source_id')->nullable()->after('operational_site_id')->constrained('sources')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_id');
        });
    }
};
