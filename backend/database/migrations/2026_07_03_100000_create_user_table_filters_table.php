<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user table filter state (sibling of user_table_preferences, ADR-0004).
 *
 * One row per (user, table domain) holding the AG Grid filterModel the user last
 * applied, so filters survive a page reload. Unlike column preferences this is
 * NOT a sparse delta: filters have no PHP "default" to diff against — the stored
 * value is the applied filterModel, keyed by column id, restricted server-side to
 * the definition's filterable columns before it is ever persisted or replayed.
 *
 * Domain-agnostic: the same table serves every domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_table_filters', function (Blueprint $table) {
            $table->id();

            // Self-scoped: a saved filter always belongs to exactly one user and is
            // removed with them. The endpoints never read user_id from the client.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The TableRegistry domain key (e.g. "users"); validated against
            // config/tables.php before anything reaches this table.
            $table->string('domain');

            // The applied AG Grid filterModel keyed by column id, e.g.
            // {"status":{"filterType":"set","values":["active"]}}.
            $table->json('filters');

            // Advanced filters (spec 0032), the backend-driven filter level above
            // the AG Grid column filters: kept in its own column so the two
            // concepts persist independently while sharing the same row.
            $table->json('advanced_filters')->nullable();

            $table->timestamps();

            // One filter state per user per table → the write is an idempotent
            // upsert, and the read is a single indexed lookup.
            $table->unique(['user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_table_filters');
    }
};
