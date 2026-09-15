<?php

declare(strict_types=1);

namespace App\RichText;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentService;
use Dom\Element;
use finfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns the images embedded in a rich text field into attachments of the
 * owning record (D-3/D-4): inline `data:` URI images are decoded, verified
 * by their REAL bytes and stored as new `rich_text` attachments; an already
 * persisted `img[data-attachment-id]` survives only if it is a `rich_text`
 * attachment of the SAME owner. Everything else about the markup (elements,
 * link schemes, mention validity) is RichTextSanitizer's job — this class
 * only ever touches `img` tags and the `rich_text` collection.
 *
 * Callers MUST run process() inside DB::transaction() together with the
 * owner's own save (images are attachments of an owner that must already
 * exist), and call deleteUnreferenced() AFTER that transaction commits.
 */
final class RichTextImageProcessor
{
    /** @var array<string, string> */
    private const array EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly RichTextSanitizer $sanitizer,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * @throws ValidationException new-image or html_max violations, none of
     *                             which leave a partial attachment/file behind
     */
    public function process(string $html, Model $owner, User $uploader, bool $allowMentions, string $field): RichTextImageResult
    {
        $body = RichTextDom::parse($html);

        // Step 1: decode/verify every inline data: URI image, then store it
        // as a new rich_text attachment of $owner and rewrite its tag.
        $createdIds = $this->storeNewImages($body, $owner, $uploader, $field);

        // Step 2: drop any data-attachment-id that isn't a rich_text
        // attachment of THIS owner (D-4) — covers stale/foreign ids in one
        // query, the ids just created in step 1 included.
        $this->keepOwnedReferences($body, $owner);

        // Step 3: final sanitize pass (allow-list, link/mention enforcement).
        $html = $this->sanitizer->sanitize(RichTextDom::serializeInner($body), $allowMentions);

        // Step 4: length guard on the persisted HTML (D-5).
        $this->assertHtmlWithinLimit($html, $field);

        return new RichTextImageResult($html, $this->referencedAttachmentIds($html), $createdIds);
    }

    /**
     * Deletes the owner's rich_text attachments (file + row) that are no
     * longer referenced after a save (D-4). Call only after the save's
     * transaction has committed.
     *
     * @param  array<int, int>  $keepIds
     */
    public function deleteUnreferenced(Model $owner, array $keepIds): void
    {
        $this->ownedAttachmentsQuery($owner)
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(fn (Attachment $attachment) => $this->attachments->delete($attachment));
    }

    /**
     * @return array<int, int> ids of the newly created attachments
     */
    private function storeNewImages(Element $body, Model $owner, User $uploader, string $field): array
    {
        $dataUriImages = array_values(array_filter(
            iterator_to_array($body->getElementsByTagName('img')),
            static fn (Element $img): bool => str_starts_with(RichTextDom::attr($img, 'src'), 'data:'),
        ));

        if ($dataUriImages === []) {
            return [];
        }

        if (count($dataUriImages) > (int) config('rich_text.max_new_images')) {
            throw ValidationException::withMessages([
                $field => ['A single save cannot embed more than '.config('rich_text.max_new_images').' new images.'],
            ]);
        }

        // Validate every image BEFORE writing anything: one corrupt/oversized
        // image must leave zero attachments behind, not the ones before it.
        $decoded = array_map(fn (Element $img): array => $this->decodeDataUri($img, $field), $dataUriImages);

        return $this->persistDecodedImages($decoded, $owner, $uploader);
    }

    /**
     * @return array{node: Element, bytes: string, mimeType: string, extension: string}
     */
    private function decodeDataUri(Element $img, string $field): array
    {
        if (! preg_match('/^data:([^;,]+);base64,(.+)$/s', RichTextDom::attr($img, 'src'), $matches)) {
            throw ValidationException::withMessages([$field => ['One of the embedded images is not a valid image.']]);
        }

        $bytes = base64_decode($matches[2], true);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([$field => ['One of the embedded images is not a valid image.']]);
        }

        $maxBytes = (int) config('rich_text.image_max_kb') * 1024;

        if (strlen($bytes) > $maxBytes) {
            throw ValidationException::withMessages([
                $field => ['Each embedded image must be at most '.config('rich_text.image_max_kb').' KB.'],
            ]);
        }

        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mimeType, (array) config('rich_text.allowed_image_mime_types'), true)) {
            throw ValidationException::withMessages([$field => ['Embedded images must be a JPEG, PNG, GIF or WebP file.']]);
        }

        return [
            'node' => $img,
            'bytes' => $bytes,
            'mimeType' => $mimeType,
            'extension' => self::EXTENSION_BY_MIME[$mimeType],
        ];
    }

    /**
     * @param  list<array{node: Element, bytes: string, mimeType: string, extension: string}>  $decoded
     * @return array<int, int>
     */
    private function persistDecodedImages(array $decoded, Model $owner, User $uploader): array
    {
        $disk = (string) config('attachments.disk');
        $directory = trim((string) config('attachments.directory'), '/');
        $writtenPaths = [];
        $createdIds = [];

        try {
            foreach ($decoded as $item) {
                $storedName = (string) Str::uuid().'.'.$item['extension'];
                $path = $directory !== '' ? $directory.'/'.$storedName : $storedName;

                Storage::disk($disk)->put($path, $item['bytes']);
                $writtenPaths[] = $path;

                $attachment = new Attachment([
                    'collection' => RichText::ATTACHMENT_COLLECTION,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $storedName,
                    'mime_type' => $item['mimeType'],
                    'extension' => $item['extension'],
                    'size' => strlen($item['bytes']),
                    'uploaded_by' => $uploader->id,
                ]);
                $attachment->attachable()->associate($owner);
                $attachment->save();

                $item['node']->removeAttribute('src');
                $item['node']->setAttribute(RichText::IMAGE_ATTR_ID, (string) $attachment->id);

                $createdIds[] = $attachment->id;
            }
        } catch (Throwable $exception) {
            foreach ($writtenPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }

        return $createdIds;
    }

    private function keepOwnedReferences(Element $body, Model $owner): void
    {
        $ownedIds = $this->ownedAttachmentsQuery($owner)->pluck('id')->all();

        foreach (iterator_to_array($body->getElementsByTagName('img')) as $img) {
            if (! $img->hasAttribute(RichText::IMAGE_ATTR_ID)) {
                continue;
            }

            $id = RichTextDom::attr($img, RichText::IMAGE_ATTR_ID);

            if (! RichText::isPositiveIntString($id) || ! in_array((int) $id, $ownedIds, true)) {
                $img->remove();
            }
        }
    }

    private function ownedAttachmentsQuery(Model $owner): Builder
    {
        return Attachment::query()
            ->where('attachable_type', $owner->getMorphClass())
            ->where('attachable_id', $owner->getKey())
            ->where('collection', RichText::ATTACHMENT_COLLECTION);
    }

    private function assertHtmlWithinLimit(string $html, string $field): void
    {
        if (mb_strlen($html) > (int) config('rich_text.html_max')) {
            throw ValidationException::withMessages([$field => ['The content is too long.']]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function referencedAttachmentIds(string $html): array
    {
        $body = RichTextDom::parse($html);
        $ids = [];

        foreach (iterator_to_array($body->getElementsByTagName('img')) as $img) {
            $id = RichTextDom::attr($img, RichText::IMAGE_ATTR_ID);

            if (RichText::isPositiveIntString($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
