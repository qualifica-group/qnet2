<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sector tree (spec 0018): unlimited-depth parent/child hierarchy, a
 * lookup used to classify Anagrafiche (no such relation yet — see spec
 * 0018 scope). `parent_id` restricts on delete (restrictOnDelete) — a
 * sector with children must be reparented/deleted first, mirroring the
 * service-level restrictive-delete guard (SectorService::delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // also resolves the SELF-referential `parent_id` remap. NULL for
            // native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name', 191);
            $table->foreignId('parent_id')->nullable()->constrained('sectors')->restrictOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
