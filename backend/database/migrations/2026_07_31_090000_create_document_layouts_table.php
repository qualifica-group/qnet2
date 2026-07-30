<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document layout entity (spec 0069): a reusable, block-based document
 * layout consumed by a module (`quotes` today, see App\Enums\DocumentLayoutModule)
 * to generate a `.docx` (spec 0070). `code` is unique GLOBALLY and immutable
 * (D-2/precedent: payment_methods), while `name` only needs to be unique
 * WITHIN a module (D-2: two modules may each have a "Standard" layout). No
 * soft delete, no `sort_order` — layouts are listed by `name` and a layout is
 * either kept or hard-deleted (D-7's default-guard is the only delete rule in
 * this spec, D-10 defers the usage guard to spec 0070).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->unique();
            $table->string('description', 500)->nullable();
            $table->string('module', 32);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('config');
            $table->timestamps();

            $table->unique(['module', 'name']);
            $table->index(['module', 'is_active']);
            $table->index(['module', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_layouts');
    }
};
