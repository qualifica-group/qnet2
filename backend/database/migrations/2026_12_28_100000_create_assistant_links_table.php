<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The link of the AI assistant is bound to the Sanctum token of the session it was opened from.
        Schema::create('assistant_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_link_id')->unique()->constrained('mcp_links')->cascadeOnDelete();
            // Null once the token is gone (logout, expiry, refresh): the link is then fallen, which is why the
            // row is kept instead of cascading away. One link per token.
            $table->foreignId('personal_access_token_id')->nullable()->unique()->constrained('personal_access_tokens')->nullOnDelete();
            // Link credential, encrypted at rest by the model cast.
            $table->text('credential');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_links');
    }
};
