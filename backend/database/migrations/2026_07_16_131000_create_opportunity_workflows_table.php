<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity workflow configurator (spec 0047): a named, activatable set of
 * criteria + working-state statuses applied to an Opportunity. `name` is
 * unique; `criteria_signature` is the deterministic, ordered
 * "field:value_id|..." string (computed by the service that syncs a
 * workflow's criteria) enforcing that no two workflows share the exact same
 * criteria combination (AC-009) — nullable because a freshly-created
 * workflow with no criteria yet has none, unique so the DB is the source of
 * truth for the invariant, not just app-layer validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('criteria_signature', 191)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_workflows');
    }
};
