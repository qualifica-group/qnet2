<?php

declare(strict_types=1);

namespace App\RichText;

/**
 * Outcome of RichTextImageProcessor::process(): the sanitized HTML ready to
 * persist, and the two attachment id sets the caller needs to finish the
 * save (sync `note_mentions`-style bookkeeping is out of scope here — this
 * is only about the `rich_text` collection).
 */
final readonly class RichTextImageResult
{
    /**
     * @param  array<int, int>  $referencedAttachmentIds  every `rich_text` attachment id still referenced by $html, in document order
     * @param  array<int, int>  $createdAttachmentIds  the subset that was just created from inline `data:` URIs in this call
     */
    public function __construct(
        public string $html,
        public array $referencedAttachmentIds,
        public array $createdAttachmentIds,
    ) {}
}
