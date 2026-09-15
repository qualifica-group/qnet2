<?php

namespace App\Services\Notes;

use App\Models\User;
use App\RichText\RichTextImageProcessor;
use App\RichText\RichTextImageResult;
use App\RichText\RichTextPlainText;
use App\RichText\RichTextSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Wraps the D-1/D-2/D-3/D-4 rich text mechanics NoteService needs around a
 * note's `body` (spec 0128), extracted out of NoteService itself to keep it
 * focused on note-specific orchestration.
 */
final class NoteBodyProcessor
{
    public function __construct(
        private readonly RichTextSanitizer $sanitizer,
        private readonly RichTextImageProcessor $imageProcessor,
    ) {}

    /**
     * D-1, mentions allowed (notes-only, D-7): used both for the D-12 mention
     * coherence check and as the PROVISIONAL body a note is first saved with
     * in NoteService::create — a brand new inline `data:` image has no
     * `data-attachment-id` yet, so this drops it; process() below restores it
     * for real, once the note exists to attach it to (D-3).
     */
    public function sanitize(string $rawBody): string
    {
        return $this->sanitizer->sanitize($rawBody, allowMentions: true);
    }

    /**
     * Extracts $rawBody's inline images into `rich_text` attachments of
     * $owner (D-3/D-4) and returns the final HTML. Re-checks D-2 on the
     * RESULT, not the input: RichTextImageProcessor::process() alone cannot
     * tell an `img` with a stripped `src` (e.g. a remote URL, neither a new
     * `data:` image nor an owned `data-attachment-id`) from a real one —
     * caller must run this inside the same DB transaction as $owner's own
     * save, so a thrown ValidationException rolls everything back.
     *
     * @throws ValidationException new-image, html_max or D-2 empty-body violations
     */
    public function process(string $rawBody, Model $owner, User $uploader, string $field): RichTextImageResult
    {
        $result = $this->imageProcessor->process($rawBody, $owner, $uploader, allowMentions: true, field: $field);

        if (RichTextPlainText::isEmpty($result->html)) {
            throw ValidationException::withMessages([$field => [__('validation.required', ['attribute' => $field])]]);
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $keepIds
     */
    public function deleteUnreferenced(Model $owner, array $keepIds): void
    {
        $this->imageProcessor->deleteUnreferenced($owner, $keepIds);
    }
}
