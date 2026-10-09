<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technical user of an external API client (spec 0210). Default false: at
 * deploy no existing user becomes a service account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_service_account')->default(false);

            $table->index('is_service_account');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_service_account']);
            $table->dropColumn('is_service_account');
        });
    }
};
