<?php

declare(strict_types=1);

namespace App\Services\TaskTemplates;

use App\Models\User;
use App\RichText\RichTextImageProcessor;
use App\RichText\RichTextPlainText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `description` rich text write-side for a task template header
 * (`TaskTemplate`) or one of its rows (`TaskTemplateItem`) — spec 0128,
 * D-1/D-2/D-3/D-4. Both models own their own `rich_text` attachments (D-6),
 * so this writer is generic over $owner rather than duplicated per model,
 * mirroring App\Services\Tasks\TaskDescriptionWriter's own split.
 *
 * Mentions are never allowed here (D-7): template descriptions are not
 * notes, so RichTextImageProcessor::process() is always called with
 * $allowMentions = false.
 */
final class TaskTemplateDescriptionWriter
{
    public function __construct(private readonly RichTextImageProcessor $images) {}

    /**
     * Sets $owner->description from the raw submitted HTML on a brand-new
     * row. $owner MUST already be persisted (has an id): any inline `data:`
     * URI image becomes one of ITS OWN attachments. The caller saves $owner
     * again if this leaves it dirty.
     */
    public function applyOnCreate(Model $owner, ?string $rawHtml, User $actor, string $field): void
    {
        [$html] = $this->process($owner, $rawHtml, $actor, $field);
        $owner->setAttribute('description', $html);
    }

    /**
     * Same as applyOnCreate() for an existing row whose `description` is
     * being (re)written, PLUS the D-4 cleanup: the owner's `rich_text`
     * attachments no longer referenced are deleted after the write
     * transaction commits. Call only from inside the same DB::transaction()
     * as $owner->save().
     */
    public function applyOnUpdate(Model $owner, ?string $rawHtml, User $actor, string $field): void
    {
        [$html, $keepIds] = $this->process($owner, $rawHtml, $actor, $field);
        $owner->setAttribute('description', $html);

        DB::afterCommit(fn () => $this->images->deleteUnreferenced($owner, $keepIds));
    }

    /**
     * Sanitizes/processes $rawHtml for $owner (D-1..D-4). Two distinct
     * inputs both collapse to "empty" (D-2, null persisted): a $rawHtml that
     * is ALREADY blank/imageless, and one that only LOOKS non-blank — e.g. a
     * lone `<img src="https://remote">`, which carries no
     * `data-attachment-id` the sanitizer can trust and is stripped entirely —
     * so the result must be re-checked AFTER process(), not assumed from the
     * raw input alone.
     *
     * @return array{0: ?string, 1: array<int, int>} the html (or null) and
     *                                               the attachment ids still
     *                                               referenced by it
     */
    private function process(Model $owner, ?string $rawHtml, User $actor, string $field): array
    {
        if ($rawHtml === null || RichTextPlainText::isEmpty($rawHtml)) {
            return [null, []];
        }

        $result = $this->images->process($rawHtml, $owner, $actor, false, $field);

        if (RichTextPlainText::isEmpty($result->html)) {
            return [null, []];
        }

        return [$result->html, $result->referencedAttachmentIds];
    }
}
