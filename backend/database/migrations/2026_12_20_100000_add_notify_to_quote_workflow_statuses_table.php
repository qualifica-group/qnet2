<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0419: the email a working status sends when a user moves an offer
 * into it (subject, message, recipients). Null = no notification, the
 * behaviour until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_workflow_statuses', function (Blueprint $table) {
            $table->json('notify')->nullable()->after('requires_note');
        });
    }

    public function down(): void
    {
        Schema::table('quote_workflow_statuses', function (Blueprint $table) {
            $table->dropColumn('notify');
        });
    }
};
