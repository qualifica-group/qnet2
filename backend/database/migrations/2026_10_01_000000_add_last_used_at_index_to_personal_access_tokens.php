<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Spec 0187: the online-users read filters tokens by last_used_at.
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['last_used_at']);
        });
    }
};
