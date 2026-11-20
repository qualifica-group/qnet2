<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalab_work_order_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('edition_id')->index();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('synced_hash', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalab_work_order_links');
    }
};
