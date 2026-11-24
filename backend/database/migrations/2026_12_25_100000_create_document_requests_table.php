<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Spec 0428: document requests of a work order, mirrored from ArchivioSmart.
| `snapshot` is the copy of the remote request (sections, items, documents),
| replaced as a whole at every read (D-3). The sync log never stores the token
| or the body.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_checklist_id')->nullable()
                ->constrained(indexName: 'document_requests_checklist_fk')->nullOnDelete();
            $table->string('checklist_code', 64);
            $table->string('checklist_name', 255);
            $table->string('category_name', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->string('recipient_email', 255)->nullable();
            $table->text('message')->nullable();

            // Remote state: null until ArchivioSmart answers the first time (D-5).
            $table->string('status', 20)->nullable();
            $table->string('title', 255)->nullable();
            $table->unsignedSmallInteger('items_count')->default(0);
            $table->unsignedSmallInteger('items_uploaded_count')->default(0);
            $table->unsignedSmallInteger('items_satisfied_count')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('last_event_at')->nullable();

            // Outbound delivery (D-4).
            $table->string('delivery_status', 20)->default('pending');
            $table->string('pending_operation', 20)->nullable();
            $table->text('delivery_error')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'document_requests_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'created_at'], 'document_requests_work_order_created_index');
        });

        Schema::create('document_request_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained(indexName: 'dr_sync_logs_request_fk')->cascadeOnDelete();
            $table->string('operation', 10);
            $table->string('outcome', 20);
            // Null = network error or connection not configured.
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('attempt');
            $table->string('message', 1000)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['document_request_id', 'created_at'], 'dr_sync_logs_request_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_sync_logs');
        Schema::dropIfExists('document_requests');
    }
};
