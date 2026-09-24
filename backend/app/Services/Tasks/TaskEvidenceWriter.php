<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\RichText\RichTextPlainText;
use App\RichText\RichTextSanitizer;

/**
 * `tasks.evidence` rich text write-side (spec 0154, D-3): "sanificato come
 * la descrizione" (D-3), but WITHOUT TaskDescriptionWriter's inline-image
 * pipeline — deliberately. `RichTextImageProcessor::deleteUnreferenced()`
 * scopes its cleanup by OWNER alone (Task), not by which of the Task's two
 * rich text columns referenced an attachment; running it a second time for
 * `evidence` would delete `description`'s own images, and vice versa. Using
 * `RichTextSanitizer` directly sidesteps the conflict rather than papering
 * over it: every `img` the sanitizer cannot verify (no
 * `data-attachment-id`, which is exactly what a freshly pasted `data:` URI
 * lacks) is stripped outright (RichTextSanitizer::enforceImages()), so
 * evidence never persists inline images and never grows the TEXT column
 * unbounded — it simply does not support embedding them, which spec 0154
 * never asked for.
 *
 * Mentions are never allowed here either (same D-7 reasoning as
 * TaskDescriptionWriter): sanitize() always runs with $allowMentions = false.
 */
final class TaskEvidenceWriter
{
    public function __construct(private readonly RichTextSanitizer $sanitizer) {}

    /**
     * Sanitizes $rawHtml and collapses a blank result to null, the same
     * "two inputs, one empty outcome" rule TaskDescriptionWriter applies:
     * an already-blank input and one that only LOOKS non-blank (e.g. a lone
     * stripped image) both persist as null.
     */
    public function sanitize(?string $rawHtml): ?string
    {
        if ($rawHtml === null || RichTextPlainText::isEmpty($rawHtml)) {
            return null;
        }

        $html = $this->sanitizer->sanitize($rawHtml, allowMentions: false);

        return RichTextPlainText::isEmpty($html) ? null : $html;
    }

    /**
     * Sets $task->evidence from the raw submitted HTML. Identical on create
     * and update (unlike TaskDescriptionWriter, which needs the Task to
     * already have an id for its image pipeline): sanitizing evidence needs
     * nothing from a persisted row, so both write paths call this one
     * method.
     */
    public function apply(Task $task, ?string $rawHtml): void
    {
        $task->evidence = $this->sanitize($rawHtml);
    }
}
