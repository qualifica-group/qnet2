<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0432: the items changed by the last rules refresh, and room in the sync
 * log for the longer operation names (refresh_rules, stop_validation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->json('rules_updated_item_ids')->nullable()->after('snapshot');
        });

        Schema::table('document_request_sync_logs', function (Blueprint $table) {
            $table->string('operation', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_request_sync_logs', function (Blueprint $table) {
            $table->string('operation', 10)->change();
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('rules_updated_item_ids');
        });
    }
};
