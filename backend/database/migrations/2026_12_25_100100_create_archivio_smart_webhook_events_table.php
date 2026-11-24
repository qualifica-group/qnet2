<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Spec 0428, D-6: one row per webhook event received from ArchivioSmart. The unique
| `event_id` makes the delivery idempotent. The body is never stored.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivio_smart_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('event', 64);
            $table->foreignId('document_request_id')->nullable()
                ->constrained(indexName: 'as_webhook_events_request_fk')->nullOnDelete();
            $table->timestamp('occurred_at')->nullable();
            $table->string('status', 20);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('archivio_smart_settings', function (Blueprint $table) {
            // Encrypted by the model cast, hence text (like the token).
            $table->text('webhook_secret')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('archivio_smart_settings', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });

        Schema::dropIfExists('archivio_smart_webhook_events');
    }
};
