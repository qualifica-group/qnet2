<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Binds a token to the API client it belongs to (spec 0210): the client key (token of its technical user) and the user tokens issued through client-login.
 * Null on every other token. Cascade: deleting the client deletes its tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_client_id');
        });
    }
};
