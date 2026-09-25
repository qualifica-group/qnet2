<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user favorite marker on a saved TableFilterView (spec 0158, D-4): a
 * user may favorite any view they can see (their own or a `shared` one),
 * independent of every other user's own favorites. Both sides cascade: the
 * favorite disappears with either the user or the view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_filter_view_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_filter_view_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'table_filter_view_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_filter_view_favorites');
    }
};
