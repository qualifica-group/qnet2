<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\DocumentBundleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Document bundle lookup entity (spec 0175, D-7c): a named set of files
 * ("Modello documenti") the Commessa email composer can attach in bulk (D-7).
 * The files themselves are plain `documents`-collection `Attachment`s owned
 * by this model (alias `document_bundle`, config/attachments.php) — this row
 * is only the header, uploaded/removed through the existing
 * `/api/attachments` endpoints. `old_id` is the legacy-migration anchor
 * (D-13, source `modello_documenti`), deliberately absent from #[Fillable] —
 * set only by property assignment post-create, mirroring `Source::old_id`.
 */
#[Fillable(['name', 'description', 'is_active'])]
class DocumentBundle extends BaseModel
{
    /** @use HasFactory<DocumentBundleFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'old_id' => 'integer',
        ];
    }
}
