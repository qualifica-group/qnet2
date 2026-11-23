<?php

namespace App\Models;

use App\Enums\DocumentChecklistSyncOperation;
use App\Enums\DocumentChecklistSyncOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of one ArchivioSmart call attempt (spec 0403, D-7).
 * Never holds the token or the request body.
 */
#[Fillable(['document_checklist_id', 'operation', 'outcome', 'http_status', 'attempt', 'message'])]
class DocumentChecklistSyncLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'operation' => DocumentChecklistSyncOperation::class,
            'outcome' => DocumentChecklistSyncOutcome::class,
            'http_status' => 'integer',
            'attempt' => 'integer',
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
