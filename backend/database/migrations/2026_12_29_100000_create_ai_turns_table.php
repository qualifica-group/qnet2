<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            // Session token the turn was started from: it opens the assistant link (no FK, the token may be gone).
            $table->unsignedBigInteger('personal_access_token_id')->nullable();
            $table->string('status', 32)->default('queued');
            $table->string('locale', 2);
            $table->longText('page_guide_text')->nullable();
            $table->longText('assistant_text')->nullable();
            $table->json('steps')->nullable();
            $table->json('usage')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_turns');
    }
};
