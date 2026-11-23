<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per ArchivioSmart call attempt for a category (spec 0410). Never stores
 * the token, the Authorization header or the request body. No cascade: categories
 * are only soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_checklist_category_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_checklist_category_id')
                ->constrained('document_checklist_categories', indexName: 'dc_category_sync_logs_category_id_foreign');
            $table->string('operation', 10);
            $table->string('outcome', 20);
            // Null = network error or connection not configured.
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('attempt');
            $table->string('message', 1000)->nullable();
            $table->timestamp('created_at')->nullable();

            // Explicit name: the generated one exceeds the MySQL 64-character identifier limit.
            $table->index(['document_checklist_category_id', 'created_at'], 'dc_category_sync_logs_category_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_checklist_category_sync_logs');
    }
};
