<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalab_call_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 16);
            $table->string('method', 10);
            $table->string('endpoint');
            $table->json('request_summary')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('outcome', 16);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('trigger', 32);
            $table->text('error_message')->nullable();
            $table->string('response_summary')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalab_call_logs');
    }
};
