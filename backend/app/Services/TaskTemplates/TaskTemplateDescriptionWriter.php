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
        $owner->setAttribute('description', $this->sanitizedOrNull($owner, $rawHtml, $actor, $field));
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
        $keepIds = [];

        if ($rawHtml !== null && ! RichTextPlainText::isEmpty($rawHtml)) {
            $result = $this->images->process($rawHtml, $owner, $actor, false, $field);
            $owner->setAttribute('description', $result->html);
            $keepIds = $result->referencedAttachmentIds;
        } else {
            $owner->setAttribute('description', null);
        }

        DB::afterCommit(fn () => $this->images->deleteUnreferenced($owner, $keepIds));
    }

    private function sanitizedOrNull(Model $owner, ?string $rawHtml, User $actor, string $field): ?string
    {
        if ($rawHtml === null || RichTextPlainText::isEmpty($rawHtml)) {
            return null;
        }

        return $this->images->process($rawHtml, $owner, $actor, false, $field)->html;
    }
}
