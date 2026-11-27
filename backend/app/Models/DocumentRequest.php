<?php

namespace App\Models;

use App\Enums\DocumentRequestDeliveryStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;

/**
 * Request of documents of a work order, mirrored from ArchivioSmart (spec 0428).
 * `external_id` towards ArchivioSmart is (string) id (D-1). Remote state fields
 * and the snapshot are written by the sync job and the webhook, never by users.
 */
#[Fillable([
    'work_order_id', 'document_checklist_id', 'checklist_code', 'checklist_name', 'category_name',
    'due_date', 'recipient_email', 'message', 'status', 'title',
    'items_count', 'items_uploaded_count', 'items_satisfied_count', 'snapshot', 'rules_updated_item_ids',
    'sent_at', 'last_reminded_at', 'completed_at', 'cancelled_at', 'expired_at', 'link_expires_at',
    'remote_updated_at', 'last_event_at', 'delivery_status', 'pending_operation', 'delivery_error', 'created_by',
])]
class DocumentRequest extends Model
{
    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory, LogsModelActivity;

    /** What the audit trail keeps: the user's choices, never the message or the recipient; remote state is written without audit. */
    private const array AUDITED_ATTRIBUTES = ['checklist_name', 'due_date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::AUDITED_ATTRIBUTES)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getTable());
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => DocumentRequestStatus::class,
            'items_count' => 'integer',
            'items_uploaded_count' => 'integer',
            'items_satisfied_count' => 'integer',
            'snapshot' => 'array',
            'rules_updated_item_ids' => 'array',
            'sent_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'link_expires_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'last_event_at' => 'datetime',
            'delivery_status' => DocumentRequestDeliveryStatus::class,
            'pending_operation' => DocumentRequestOperation::class,
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<DocumentChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(DocumentChecklist::class, 'document_checklist_id');
    }

    /**
     * @return HasMany<DocumentRequestSyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(DocumentRequestSyncLog::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, DocumentRequestStatus::closed(), true);
    }

    /**
     * Sent or in progress: the states in which a file can be reviewed and the request closed early (spec 0429).
     */
    public function isReviewable(): bool
    {
        return $this->isRemindable();
    }

    /**
     * ArchivioSmart never answered and no call is queued: the request exists only
     * in QNet 2, so every remote call would answer 404 (spec 0428, D-5 revision 2026-11-25).
     */
    public function neverReachedArchivioSmart(): bool
    {
        return $this->status === null && $this->delivery_status !== DocumentRequestDeliveryStatus::Pending;
    }

    /**
     * Draft, sent or in progress: ArchivioSmart can copy the current checklist rules into it (spec 0432).
     */
    public function acceptsRulesRefresh(): bool
    {
        return in_array($this->status, DocumentRequestStatus::open(), true);
    }

    /**
     * The item of the snapshot with this id, or null.
     *
     * @return array<string, mixed>|null
     */
    public function snapshotItem(int $itemId): ?array
    {
        $item = collect($this->snapshot['items'] ?? [])->firstWhere('id', $itemId);

        return is_array($item) ? $item : null;
    }

    public function isRemindable(): bool
    {
        return in_array($this->status, DocumentRequestStatus::remindable(), true);
    }
}
