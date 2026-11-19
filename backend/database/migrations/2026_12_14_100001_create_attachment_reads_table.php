<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has seen which attachment (the unread badge of the grids' row action): one row
 * per user and attachment, written when the user opens the list it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_reads', function (Blueprint $table): void {
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['attachment_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_reads');
    }
};
