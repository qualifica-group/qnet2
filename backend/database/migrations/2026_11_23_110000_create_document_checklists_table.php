<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document checklists synced to ArchivioSmart (spec 0403). Soft delete keeps the
 * row after the remote DELETE so the id (the external_id) is never reused (D-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            // Locks the code once a sync succeeded (D-4).
            $table->timestamp('first_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_checklists');
    }
};
