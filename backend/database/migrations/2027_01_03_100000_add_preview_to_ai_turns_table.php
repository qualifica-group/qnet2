<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_turns', function (Blueprint $table): void {
            // Preview of a write the turn waits for (spec 0433, AiPreview): connector content, never model text.
            $table->json('preview')->nullable()->after('steps');
            // What the turn needs to go on after the wait: messages so far, counters, result to hand to the model.
            $table->longText('resume_state')->nullable()->after('preview');
        });
    }

    public function down(): void
    {
        Schema::table('ai_turns', function (Blueprint $table): void {
            $table->dropColumn(['preview', 'resume_state']);
        });
    }
};
