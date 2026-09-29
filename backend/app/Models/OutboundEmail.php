<?php

namespace App\Models;

use App\Enums\OutboundEmailStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use Database\Factories\OutboundEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One email drafted/sent from an owning record (spec 0175, D-2): polymorphic
 * `emailable` so the same table serves Quote/Opportunity emails later (D-10)
 * without a migration, though `WorkOrder::outboundEmails()` is the only
 * producer wired this version. Deliberately NO LogsModelActivity (D-15): the
 * commessa's activity log carries no entry for a send, the "Email" tab IS the
 * history.
 *
 * `status`/`sender_user_id`/`from_address`/`queued_at`/`sent_at`/`failed_at`/
 * `error_message` are ALL service-managed state, deliberately absent from
 * #[Fillable] — never reachable through a raw mass-assignment of request
 * data, only ever written by `OutboundEmailService`'s own transitions
 * (create as draft, queue, mark sent/failed), mirroring `WorkOrder::code`.
 * Only the composer's own content fields are client-writable.
 */
#[Fillable(['email_template_id', 'to_recipients', 'cc_recipients', 'bcc_recipients', 'subject', 'body'])]
class OutboundEmail extends BaseModel
{
    /** @use HasFactory<OutboundEmailFactory> */
    use HasAttachments, HasFactory;

    /**
     * Attachment collection reserved for an email's own allegati (D-7/D-8):
     * closed off to the generic `/api/attachments` endpoints
     * (AttachmentPolicy carve-out) — reachable only through the nested
     * `work-orders/{workOrder}/emails/{email}/attachments/*` endpoints,
     * authorized on the owning Commessa.
     */
    public const string ATTACHMENT_COLLECTION = 'email_attachments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutboundEmailStatus::class,
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * The record this email belongs to — a WorkOrder today, Quote/Opportunity
     * possibly later (D-10).
     */
    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The template the composer resolved from, if any (D-4). Nullable +
     * nullOnDelete: removing a template never removes the emails written
     * from it — the resolved subject/body already live on this row.
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * The user who authored/sent this email (D-6). restrictOnDelete at the
     * schema: an email's author is never actually removable.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * The actor-visibility rule (spec 0175, D-3): a `draft` is visible ONLY
     * to its own author; `queued`/`sent`/`failed` are visible to anyone the
     * caller already knows holds `work-orders.viewEmails` on this commessa
     * (this scope narrows WITHIN that set, it never substitutes for the
     * WorkOrderPolicy gate). Applied to every lookup a nested email endpoint
     * performs — list, single-record resolution, attachment resolution — so
     * a draft belonging to someone else is never found at all rather than
     * found-then-rejected: BE-05's own routing decision (not the frozen
     * data_contract's literal per-endpoint status code) is that a hidden
     * draft answers 404 everywhere, uniformly, never 403 — see
     * OutboundEmailService's own docblock for the full reasoning.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return $query->where(function (Builder $visible) use ($actor): void {
            $visible->where('status', '!=', OutboundEmailStatus::Draft->value)
                ->orWhere('sender_user_id', $actor->id);
        });
    }

    /**
     * OutboundEmailService::send()'s transition into the queue (D-12):
     * `from_address` is the snapshot of the sender's OWN mailbox at send
     * time (D-6); `error_message` is cleared so a resent `failed` email
     * does not keep showing its previous failure once it is queued again.
     */
    public function markQueued(string $fromAddress): void
    {
        $this->forceFill([
            'status' => OutboundEmailStatus::Queued,
            'from_address' => $fromAddress,
            'queued_at' => now(),
            'error_message' => null,
        ])->save();
    }

    /**
     * SendOutboundEmailJob's success transition (D-12): `status`/`sent_at`
     * are deliberately outside #[Fillable] (see the class docblock), so this
     * is the only path that ever sets them.
     */
    public function markSent(): void
    {
        $this->forceFill([
            'status' => OutboundEmailStatus::Sent,
            'sent_at' => now(),
        ])->save();
    }

    /**
     * SendOutboundEmailJob's failure transition (D-12): `$message` is
     * ALWAYS a safe, translated string built by the caller (GraphMailException
     * or a generic fallback) -- never a raw exception message, which could
     * carry a Graph response body or other internal detail.
     */
    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => OutboundEmailStatus::Failed,
            'failed_at' => now(),
            'error_message' => $message,
        ])->save();
    }
}
