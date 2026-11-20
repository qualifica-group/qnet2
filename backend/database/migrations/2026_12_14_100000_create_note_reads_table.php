<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has seen which note (the unread badge of the grids' row action): one row
 * per user and note, written when the user opens the list it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_reads', function (Blueprint $table): void {
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['note_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_reads');
    }
};
