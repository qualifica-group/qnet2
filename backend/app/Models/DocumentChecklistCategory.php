<?php

namespace App\Models;

use App\Enums\DocumentChecklistSyncStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\DocumentChecklistCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;

/**
 * Category of the document checklists, synced to ArchivioSmart (spec 0410).
 * Soft deleted so the id (the remote external_id) is never reused (D-5).
 */
#[Fillable(['name', 'description', 'sort_order', 'sync_status', 'sync_error', 'synced_at'])]
class DocumentChecklistCategory extends BaseModel
{
    /** @use HasFactory<DocumentChecklistCategoryFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    /** Written by the sync job, not by users: kept out of the activity log. */
    private const SYSTEM_MANAGED_ATTRIBUTES = ['sync_status', 'sync_error', 'synced_at'];

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
            'sort_order' => 'integer',
            'sync_status' => DocumentChecklistSyncStatus::class,
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Non-deleted checklists only (the checklist model's global scope).
     *
     * @return HasMany<DocumentChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(DocumentChecklist::class, 'category_id');
    }

    /**
     * @return HasMany<DocumentChecklistCategorySyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(DocumentChecklistCategorySyncLog::class);
    }
}
