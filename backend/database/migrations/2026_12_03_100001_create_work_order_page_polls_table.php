<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_page_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_page_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->json('options');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_page_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_page_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('option_key');
            $table->timestamps();

            $table->unique(['work_order_page_poll_id', 'user_id'], 'work_order_page_poll_votes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_page_poll_votes');
        Schema::dropIfExists('work_order_page_polls');
    }
};
