<?php

namespace App\Models;

use Database\Factories\DocumentChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One document requested by a checklist (spec 0403); `position` is the display order.
 * `allowed_file_types` holds DocumentChecklistFileType values.
 */
#[Fillable([
    'document_checklist_id', 'position', 'name', 'description', 'section', 'is_required',
    'allowed_file_types', 'max_file_size_kb', 'min_files', 'max_files', 'validation_prompt', 'max_age_days',
])]
class DocumentChecklistItem extends Model
{
    /** @use HasFactory<DocumentChecklistItemFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_required' => 'boolean',
            'allowed_file_types' => 'array',
            'max_file_size_kb' => 'integer',
            'min_files' => 'integer',
            'max_files' => 'integer',
            'max_age_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DocumentChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(DocumentChecklist::class, 'document_checklist_id');
    }
}
