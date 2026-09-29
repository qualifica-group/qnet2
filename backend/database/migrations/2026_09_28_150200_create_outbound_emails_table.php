<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email sent (or drafted) from a Commessa (spec 0175, D-2):
 * polymorphic `emailable` so the same table serves Quote/Opportunity emails
 * later (D-10) without a migration, though only `work_order` is wired this
 * version. Lifecycle `draft -> queued -> sent | failed` lives in `status`
 * (App\Enums\OutboundEmailStatus); `queued_at`/`sent_at`/`failed_at` record
 * each transition, `error_message` the last Graph failure (D-6, never the
 * raw Graph response — see constraints). `from_address` is a snapshot of the
 * sender's mailbox at send time, kept alongside the immutable
 * `sender_user_id` (restrictOnDelete: an email's author is never actually
 * removable). Allegati live in `attachments` (alias `outbound_email`,
 * collection `email_attachments`, D-7/D-8) via HasAttachments on the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_emails', function (Blueprint $table) {
            $table->id();
            $table->string('emailable_type');
            $table->unsignedBigInteger('emailable_id');
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_address', 191)->nullable();
            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->json('bcc_recipients')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['emailable_type', 'emailable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_emails');
    }
};
