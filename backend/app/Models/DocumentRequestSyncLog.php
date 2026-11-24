<?php

namespace App\Models;

use App\Enums\DocumentChecklistSyncOutcome;
use App\Enums\DocumentRequestOperation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of one ArchivioSmart call attempt of a document request
 * (spec 0428). Never holds the token, the body or personal data.
 */
#[Fillable(['document_request_id', 'operation', 'outcome', 'http_status', 'attempt', 'message'])]
class DocumentRequestSyncLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'operation' => DocumentRequestOperation::class,
            'outcome' => DocumentChecklistSyncOutcome::class,
            'http_status' => 'integer',
            'attempt' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }
}
