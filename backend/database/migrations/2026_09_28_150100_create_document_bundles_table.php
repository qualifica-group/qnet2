<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document bundle lookup entity (spec 0175, D-7c): a named set of files
 * ("Modello documenti") attachable in bulk to an Commessa email — the files
 * themselves live in the existing `attachments` table (alias `document_bundle`,
 * collection `documents`, see config/attachments.php), this row is only the
 * header. `old_id` is the migration anchor for the legacy `modello_documenti`
 * source (D-13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('old_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_bundles');
    }
};
