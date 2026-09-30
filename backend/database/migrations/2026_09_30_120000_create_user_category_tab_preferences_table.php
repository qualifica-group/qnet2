<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user favourite categories of the Gestione Richieste / Gestione Iscritti
 * category tab strip (spec 0184): one row per (user, module). Self-scoped UI
 * state like `user_table_preferences` (ADR-0004): removed with the user, never
 * keyed on a client-supplied user id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_category_tab_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // RequestModule value, resolved from the matched route, never from input.
            $table->string('module', 40);

            // Product category ids. JSON, not a pivot: read and replaced whole,
            // and a deleted category is simply dropped on read.
            $table->json('favorite_category_ids');
            $table->boolean('show_only_favorites')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_category_tab_preferences');
    }
};
