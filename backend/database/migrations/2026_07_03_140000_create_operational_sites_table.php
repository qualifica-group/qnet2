<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational site entity (spec 0011 — "Sedi operative"): a physical
 * location identified entirely by its address (comune/via/CAP/provincia/
 * regione). No own name/label column — the site IS its address, which lives
 * on the polymorphic `addresses` table (HasAddresses), never as flat columns
 * here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_sites', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            // The site's own free-text label — the legacy system exposes it in
            // its `comune` field (e.g. "FRATTAMAGGIORE 1 (HQ)"), which is a name,
            // not a real city. Its single own column besides timestamps/old_id.
            $table->string('alias')->nullable();

            $table->timestamps();

            // Default sort on created_at (spec 0011).
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_sites');
    }
};
