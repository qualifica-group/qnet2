<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Services\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for a document layout's own uploaded images (spec 0069,
 * MT-4): list/upload/delete against the `layout_image` attachment collection.
 * Persisting/removing the binary itself is delegated to AttachmentService —
 * the canonical upload/delete path shared with every other attachment
 * consumer (putFileAs with a UUID name, metadata row in a transaction, orphan
 * binary rolled back on failure). This service only adds the two rules
 * specific to a layout's images: the MAX_IMAGES_PER_LAYOUT cap on upload and
 * the `image_in_use` config-reference guard on delete.
 */
class DocumentLayoutImageService
{
    public function __construct(private readonly AttachmentService $attachmentService) {}

    /**
     * @return Collection<int, Attachment>
     */
    public function list(DocumentLayout $documentLayout): Collection
    {
        return $documentLayout->images()->latest()->get();
    }

    public function store(DocumentLayout $documentLayout, UploadedFile $file): Attachment
    {
        if ($documentLayout->images()->count() >= DocumentLayoutConfigLimits::MAX_IMAGES_PER_LAYOUT) {
            throw ValidationException::withMessages([
                'file' => ['This layout already has the maximum of '.DocumentLayoutConfigLimits::MAX_IMAGES_PER_LAYOUT.' images.'],
            ]);
        }

        return $this->attachmentService->storeFor($documentLayout, $file, DocumentLayout::IMAGE_COLLECTION);
    }

    /**
     * Deletes $attachment, guarded by two checks:
     *  - ownership: resolved through the layout's OWN `images()` relation, so
     *    an attachment belonging to another layout (or to another collection
     *    entirely) never confirms its existence — ModelNotFoundException ->
     *    404, not 403 (AC-094);
     *  - `image_in_use`: the layout's CURRENT config may still reference this
     *    attachment_id from an `image` block — the block must be removed
     *    first (AC-095).
     */
    public function delete(DocumentLayout $documentLayout, Attachment $attachment): void
    {
        /** @var Attachment $owned */
        $owned = $documentLayout->images()->findOrFail($attachment->getKey());

        $this->assertNotReferencedByConfig($documentLayout, $owned);

        $this->attachmentService->delete($owned);
    }

    private function assertNotReferencedByConfig(DocumentLayout $documentLayout, Attachment $attachment): void
    {
        foreach (['header', 'body', 'footer'] as $zone) {
            $blocks = $documentLayout->config[$zone]['blocks'] ?? [];

            foreach ($blocks as $block) {
                if (($block['type'] ?? null) === 'image' && ($block['attachment_id'] ?? null) === $attachment->id) {
                    throw ValidationException::withMessages([
                        'attachment_id' => [__('document_layouts.image_in_use')],
                    ]);
                }
            }
        }
    }
}
