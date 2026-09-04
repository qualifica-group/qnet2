<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task type lookup entity (spec 0101, D-4): one of the four PURE Task
 * configurators (Tipologia, Categoria, Priorita', Importanza) that share the
 * exact same shape — no `system_key`, no numeric weight, no protected row.
 * Every row is renameable/deletable, guarded only by "in use" (D-8).
 *
 * `color` is a token of the shared `BADGE_COLOR_TOKENS` palette (not a hex
 * value), `icon` a lucide kebab-case name of `ICON_NAMES` — both validated
 * server-side against an allow-list, never free text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_types');
    }
};
