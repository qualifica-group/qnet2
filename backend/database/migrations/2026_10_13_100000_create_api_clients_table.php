<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration clients of the QNet API (specs 0209, 0210). The key is a Sanctum
 * token of the client's technical user (personal_access_tokens.api_client_id);
 * only its last four characters are kept here, to recognise it in the UI.
 * restrictOnDelete on the technical user: it is kept (deactivated) when the
 * client is deleted, so the activity log causer never dangles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('rate_limit_per_minute')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('key_last_four', 4)->nullable();
            $table->foreignId('service_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
