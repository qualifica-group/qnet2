<?php

namespace App\Models;

use Database\Factories\DocumentChecklistSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of documents of a checklist (spec 0427); `position` is the display order.
 * Deleting a section leaves its items without group (section_id nullOnDelete).
 */
#[Fillable(['document_checklist_id', 'name', 'description', 'position'])]
class DocumentChecklistSection extends Model
{
    /** @use HasFactory<DocumentChecklistSectionFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<DocumentChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(DocumentChecklist::class, 'document_checklist_id');
    }

    /**
     * @return HasMany<DocumentChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentChecklistItem::class, 'section_id');
    }
}
