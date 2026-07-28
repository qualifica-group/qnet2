<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved filter views (spec 0007), sibling of user_table_filters.
 *
 * Unlike user_table_filters (one applied filterModel per user per domain), a
 * user may save MANY named views per domain, each either private (owner only)
 * or shared (viewable/appliable by every user who can view the table). This is
 * a real cross-user access surface, so — unlike user_table_filters/
 * user_table_preferences — it IS backed by a Policy (TableFilterViewPolicy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_filter_views', function (Blueprint $table) {
            $table->id();

            // Owner: removed with the user, never left orphaned.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The TableRegistry domain key (e.g. "users"); validated against
            // config/tables.php before anything reaches this table.
            $table->string('domain');

            $table->string('name', 80);

            // The saved AG Grid filterModel keyed by column id, restricted
            // server-side to the definition's filterable columns on every write
            // and re-filtered on every read (TableFilterViewRequest / Service).
            $table->json('filters');

            // Advanced filters (spec 0032), the backend-driven filter level above
            // the AG Grid column filters: kept in its own column so the two
            // concepts persist independently while sharing the same row.
            $table->json('advanced_filters')->nullable();

            // 'private' | 'shared' — see App\Enums\FilterViewVisibility.
            $table->string('visibility')->default('private');

            $table->timestamps();

            // One view name per user per domain (a user cannot save two views
            // with the same name for the same table).
            $table->unique(['user_id', 'domain', 'name']);

            // Speeds up the "other users' shared views for this domain" half of
            // the list query.
            $table->index(['domain', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_filter_views');
    }
};
