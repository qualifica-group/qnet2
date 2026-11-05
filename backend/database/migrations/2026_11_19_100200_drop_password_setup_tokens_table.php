<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The invite-link flow (spec 0177, broker `users_setup`) is gone (spec 0355):
     * the welcome email now carries a generated password.
     */
    public function up(): void
    {
        Schema::dropIfExists('password_setup_tokens');
    }

    public function down(): void
    {
        Schema::create('password_setup_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
