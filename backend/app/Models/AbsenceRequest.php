<?php

namespace App\Models;

use App\Enums\AbsenceRequestStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AbsenceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Absence request (spec 0278). `minutes` is frozen at submission (D-6);
 * `approvers` is the snapshot taken then (D-7). The optional attachment is
 * served by dedicated endpoints, never by the generic attachments API (D-12).
 */
#[Fillable([
    'user_id', 'absence_type_id', 'created_by', 'status', 'is_full_day', 'starts_on', 'ends_on',
    'minutes', 'requested_minutes', 'note', 'rejection_reason', 'decided_by', 'decided_at',
    'submitted_at', 'cancelled_by', 'cancelled_at',
])]
class AbsenceRequest extends BaseModel
{
    /** @use HasFactory<AbsenceRequestFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => AbsenceRequestStatus::class,
            'is_full_day' => 'boolean',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'minutes' => 'integer',
            'requested_minutes' => 'integer',
            'decided_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AbsenceType, $this>
     */
    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'absence_request_approver', 'absence_request_id', 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
