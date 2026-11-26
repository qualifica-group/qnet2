<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memory of the conversation (spec 0433, D-5): the summary of the old part, how many times it was
 * rewritten (after a configured number the assistant proposes a new conversation) and the id of the
 * last message the summary covers (messages after it are sent whole).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('summary_count')->default(0);
            $table->unsignedBigInteger('summary_through_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['summary', 'summary_count', 'summary_through_message_id']);
        });
    }
};
