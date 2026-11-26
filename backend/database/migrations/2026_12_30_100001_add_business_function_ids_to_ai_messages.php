<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the person's business functions taken when the message that starts a turn is saved
 * (spec 0433, D-10): every model call of the turn is costed against these, even if the membership
 * changes while the turn waits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->json('business_function_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('business_function_ids');
        });
    }
};
