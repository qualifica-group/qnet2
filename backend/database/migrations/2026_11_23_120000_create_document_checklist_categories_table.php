<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document checklist categories synced to ArchivioSmart (spec 0410). Soft delete
 * keeps the row after the remote DELETE so the id (the external_id) is never reused (D-5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_checklist_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_checklist_categories');
    }
};
