<?php

declare(strict_types=1);

namespace App\RichText;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;

/**
 * Copies the `rich_text` images referenced by $source's HTML onto $target
 * (D-8): `TaskOccurrenceFactory` (recurrences) and `WorkOrderTaskGenerator`
 * (task templates -> generated tasks) both need a generated record's images
 * to be its OWN attachments, not a second reference to the source's files.
 * An id with no matching source attachment is dropped, never carried over.
 */
final class RichTextAttachmentCopier
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function copy(?string $html, Model $source, Model $target, User $uploader): ?string
    {
        if ($html === null) {
            return null;
        }

        $body = RichTextDom::parse($html);
        $sourceAttachments = $this->sourceAttachmentsById($source);

        foreach (iterator_to_array($body->getElementsByTagName('img')) as $img) {
            $id = RichTextDom::attr($img, RichText::IMAGE_ATTR_ID);
            $attachment = RichText::isPositiveIntString($id) ? ($sourceAttachments[(int) $id] ?? null) : null;

            if ($attachment === null) {
                $img->remove();

                continue;
            }

            $copy = $this->attachments->copyTo($attachment, $target, RichText::ATTACHMENT_COLLECTION, $uploader);
            $img->setAttribute(RichText::IMAGE_ATTR_ID, (string) $copy->id);
        }

        return RichTextDom::serializeInner($body);
    }

    /**
     * @return array<int, Attachment>
     */
    private function sourceAttachmentsById(Model $source): array
    {
        return Attachment::query()
            ->where('attachable_type', $source->getMorphClass())
            ->where('attachable_id', $source->getKey())
            ->where('collection', RichText::ATTACHMENT_COLLECTION)
            ->get()
            ->keyBy('id')
            ->all();
    }
}
