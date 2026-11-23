<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $table->string('mcp_client', 120);
            // SHA-256 of the link credential: the credential itself is never stored.
            $table->char('credential_hash', 64)->unique();
            // SHA-256 of the password hash at opening: a different value means the password changed.
            $table->char('password_fingerprint', 64);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        // Which user tokens each link issued; deleting a token drops its row.
        Schema::create('mcp_link_tokens', function (Blueprint $table): void {
            $table->foreignId('mcp_link_id')->constrained('mcp_links')->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->primary(['mcp_link_id', 'personal_access_token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_link_tokens');
        Schema::dropIfExists('mcp_links');
    }
};
