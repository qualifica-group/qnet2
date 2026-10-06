<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `purpose` (spec 0195, D-11): document|reminder, NULL for the existing work
 * order emails. The composite index serves the "last reminder sent" lookup
 * per emailable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbound_emails', function (Blueprint $table) {
            $table->string('purpose', 16)->nullable()->after('status');
            $table->index(['emailable_type', 'emailable_id', 'purpose'], 'outbound_emails_emailable_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_emails', function (Blueprint $table) {
            $table->dropIndex('outbound_emails_emailable_purpose_index');
            $table->dropColumn('purpose');
        });
    }
};
