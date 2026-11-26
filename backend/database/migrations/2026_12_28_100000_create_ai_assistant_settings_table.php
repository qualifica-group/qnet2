<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings of the AI assistant (spec 0433): model, OpenRouter routing,
 * additional instructions, general switch. The OpenRouter key is NOT stored here
 * (it stays in the server environment). The row is created on first read with the
 * initial values of config/ai-assistant.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('model_id', 120);
            $table->text('instructions');
            $table->boolean('routing_zdr');
            $table->string('routing_data_collection', 8);
            $table->boolean('routing_require_parameters');
            $table->string('routing_sort', 12);
            $table->decimal('spend_cap_person_usd', 12, 2)->nullable();
            $table->decimal('spend_cap_total_usd', 12, 2)->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_settings');
    }
};
