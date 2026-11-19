<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * Drop-in polymorphic file attachments for any model.
 *
 * Add `use HasAttachments` to an owning model and everything is wired
 * automatically — no schema change (the `attachments` table already carries a
 * nullable `attachable` morph), no extra setup:
 *
 *     class Invoice extends BaseModel
 *     {
 *         use HasAttachments;
 *     }
 *
 *     $invoice->attach($uploadedFile);            // store + link in one call
 *     $invoice->attach($file, 'scans');           // grouped in a collection
 *     $invoice->attachments;                       // all files owned
 *     $invoice->attachments()->where('collection', 'scans')->get();
 *     $invoice->delete();                          // cascades: rows + binaries
 *
 * Business logic stays in AttachmentService (models remain thin); the trait is
 * just the convenient owner-side surface.
 */
trait HasAttachments
{
    /**
     * Auto-wiring: when the owner is deleted, its attachments and their stored
     * binaries are removed too, so no orphan files or rows are left behind.
     *
     * Skipped on a soft-delete (the owner still exists); cleanup runs on a real
     * delete or force-delete.
     */
    public static function bootHasAttachments(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $service = app(AttachmentService::class);

            $model->attachments()->get()->each(
                static fn (Attachment $attachment) => $service->delete($attachment)
            );
        });
    }

    /**
     * All files owned by this model.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Only the user-uploaded documents: no avatar, logo or rich text image.
     */
    public function documents(): MorphMany
    {
        return $this->attachments()->where('collection', Attachment::DOCUMENTS_COLLECTION);
    }

    /**
     * Store an uploaded file and link it to this model in a single call.
     */
    public function attach(UploadedFile $file, ?string $collection = null): Attachment
    {
        return app(AttachmentService::class)->storeFor($this, $file, $collection);
    }
}
