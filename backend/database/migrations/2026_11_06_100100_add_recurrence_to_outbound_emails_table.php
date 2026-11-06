<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the copies a recurring series sends (spec 0362, D-6/D-10): the
 * UNIQUE pair makes one occurrence per series idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbound_emails', function (Blueprint $table) {
            $table->foreignId('outbound_email_recurrence_id')->nullable()->after('purpose')
                ->constrained('outbound_email_recurrences')->nullOnDelete();
            $table->date('occurrence_on')->nullable()->after('outbound_email_recurrence_id');
            $table->unique(['outbound_email_recurrence_id', 'occurrence_on'], 'outbound_emails_recurrence_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('outbound_emails', function (Blueprint $table) {
            $table->dropUnique('outbound_emails_recurrence_occurrence_unique');
            $table->dropConstrainedForeignId('outbound_email_recurrence_id');
            $table->dropColumn('occurrence_on');
        });
    }
};
