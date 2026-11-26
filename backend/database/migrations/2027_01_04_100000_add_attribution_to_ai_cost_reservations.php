<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An open reservation carries what is needed to book its cost if the worker never settles it
 * (spec 0433, D-7): the conversation and the business functions the turn is attributed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_cost_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->json('business_function_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_cost_reservations', function (Blueprint $table) {
            $table->dropColumn(['conversation_id', 'business_function_ids']);
        });
    }
};
