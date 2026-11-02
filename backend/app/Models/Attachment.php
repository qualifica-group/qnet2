<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reusable, polymorphic file attachment.
 *
 * Stores only metadata; the binary lives on the `disk` at `path`. Attach to any
 * owning model with the HasAttachments trait (morphMany on `attachable`), e.g.:
 *
 *     class Invoice extends BaseModel
 *     {
 *         use HasAttachments;
 *     }
 *
 *     $invoice->attachments;
 *
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $external_url
 * @property string|null $old_source
 * @property int|null $old_id
 */
class Attachment extends BaseModel
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * Pseudo-disk of a link-only legacy document (spec 0295 D-10): no
     * filesystem disk carries this name, so a binary read that forgets
     * isLegacyLink() fails loudly instead of reading a wrong file.
     */
    public const string LEGACY_DISK = 'legacy';

    protected $fillable = [
        'collection',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'collection' => 'string',
        'disk' => 'string',
        'path' => 'string',
        'original_name' => 'string',
        'mime_type' => 'string',
        'extension' => 'string',
        'size' => 'int',
        'uploaded_by' => 'int',
        'external_url' => 'string',
        'old_source' => 'string',
        'old_id' => 'int',
    ];

    /**
     * A migrated legacy document whose file stays on the legacy QNet and is
     * streamed through LegacyAttachmentFetcher (spec 0295 D-2).
     */
    public function isLegacyLink(): bool
    {
        return $this->external_url !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
