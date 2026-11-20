<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalab_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('trigger', 32);
            $table->string('outcome', 16);
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('classes_synced')->default(0);
            $table->unsignedInteger('work_orders_updated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalab_sync_runs');
    }
};
