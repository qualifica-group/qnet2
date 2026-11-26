<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost ledger of the AI assistant (spec 0433, D-7 and D-10). The ledger is kept apart from the
 * conversations: user, conversation and business function are plain ids with NO foreign key, so
 * deleting a conversation, a person or a function never touches a cost row. Amounts are dollars
 * with 12 decimals (OpenRouter prices are fractions of a cent per token).
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per call to the model (summaries included).
        Schema::create('ai_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('model_id', 120);
            $table->string('provider', 120)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->decimal('cost_usd', 20, 12);
            $table->decimal('reserved_usd', 20, 12);
            // cost_above_reservation | cost_missing: anomalies for the super-admin to read.
            $table->string('signal', 32)->nullable();
            $table->date('incurred_on');
            $table->timestamps();

            $table->index('incurred_on');
            $table->index(['user_id', 'incurred_on']);
        });

        // Equal share of one entry per business function of the person; null = "no function".
        Schema::create('ai_cost_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cost_entry_id')->constrained('ai_cost_entries')->cascadeOnDelete();
            $table->unsignedBigInteger('business_function_id')->nullable();
            $table->decimal('cost_usd', 20, 12);

            $table->index('business_function_id');
        });

        // Month counters: user_id 0 is the company total. Rows are the lock target of reservations.
        Schema::create('ai_spend_counters', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->decimal('spent_usd', 20, 12)->default(0);
            $table->timestamps();

            $table->unique(['period', 'user_id']);
        });

        // Open reservations: summed with the spent amount against the caps.
        Schema::create('ai_cost_reservations', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7);
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount_usd', 20, 12);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['period', 'user_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cost_reservations');
        Schema::dropIfExists('ai_spend_counters');
        Schema::dropIfExists('ai_cost_shares');
        Schema::dropIfExists('ai_cost_entries');
    }
};
