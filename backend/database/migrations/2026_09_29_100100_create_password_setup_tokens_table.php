<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invite tokens of the `users_setup` broker (spec 0177). Kept apart from
     * `password_reset_tokens` so an invite and a reset token never overwrite or
     * validate each other.
     */
    public function up(): void
    {
        Schema::create('password_setup_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_setup_tokens');
    }
};
