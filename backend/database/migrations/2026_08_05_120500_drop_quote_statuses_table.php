<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0083 (D-1): the flat `quote-statuses` module (spec 0065) is replaced
 * outright by the criteria-resolved `quote_workflow_statuses` set — the FK
 * that referenced this table (`quotes.quote_status_id`) was already dropped
 * by
 * `2026_08_05_120300_replace_quote_status_id_with_quote_workflow_status_id_on_quotes_table`,
 * so nothing references this table any more.
 *
 * DESTRUCTIVE: every custom quote-status row is lost. `down()` recreates the
 * EMPTY table (structure only, not even the 3 former system rows: nothing
 * reads them any more), same precedent as
 * `2026_08_05_110100_drop_opportunity_statuses_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('quote_statuses');
    }

    public function down(): void
    {
        Schema::create('quote_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('system_key', 16)->nullable()->unique();
            $table->string('group', 16)->default('open');
            $table->timestamps();
        });
    }
};
