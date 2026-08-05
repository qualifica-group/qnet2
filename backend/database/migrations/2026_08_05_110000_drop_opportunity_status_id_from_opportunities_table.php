<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0082: the Opportunity's status is no longer a stored pick — it is
 * COMPUTED from the statuses of its Quotes (falling back to its "stato di
 * lavorazione" when it has none), so the FK to `opportunity_statuses` and its
 * restrictOnDelete guard go away.
 *
 * DESTRUCTIVE: the per-opportunity status assignment is not recoverable.
 * `down()` restores the STRUCTURE only — and NULLABLE, since the original
 * NOT NULL cannot be honoured without inventing values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['opportunity_status_id']);
            $table->dropColumn('opportunity_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('opportunity_status_id')
                ->nullable()
                ->constrained('opportunity_statuses')
                ->restrictOnDelete();
        });
    }
};
