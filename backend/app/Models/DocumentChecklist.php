<?php

namespace App\Models;

use App\Enums\DocumentChecklistSyncStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\DocumentChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;

/**
 * Document checklist synced to ArchivioSmart (spec 0403). Soft deleted so the id
 * (the remote external_id) is never reused (D-3); the code is locked once a sync
 * succeeded (D-4).
 */
#[Fillable([
    'category_id', 'name', 'code', 'description', 'is_active', 'sync_status', 'sync_error', 'synced_at', 'first_synced_at',
])]
class DocumentChecklist extends BaseModel
{
    /** @use HasFactory<DocumentChecklistFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    /** Written by the sync job, not by users: kept out of the activity log. */
    private const SYSTEM_MANAGED_ATTRIBUTES = ['sync_status', 'sync_error', 'synced_at', 'first_synced_at'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept([...$this->getHidden(), ...self::SYSTEM_MANAGED_ATTRIBUTES])
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
            'is_active' => 'boolean',
            'sync_status' => DocumentChecklistSyncStatus::class,
            'synced_at' => 'datetime',
            'first_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentChecklistCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentChecklistCategory::class, 'category_id');
    }

    /**
     * @return HasMany<DocumentChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentChecklistItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<DocumentChecklistSyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(DocumentChecklistSyncLog::class);
    }

    public function isCodeLocked(): bool
    {
        return $this->first_synced_at !== null;
    }
}
