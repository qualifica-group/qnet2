<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\RichText\RichTextImageProcessor;
use App\RichText\RichTextPlainText;
use Illuminate\Support\Facades\DB;

/**
 * `tasks.description` rich text write-side (spec 0128, D-1/D-2/D-3/D-4):
 * split out of TaskService (engineering.md §6, file size) since the two
 * write paths need two different shapes of the same idea — create() has no
 * prior attachments to reconcile, update() does.
 *
 * Mentions are never allowed here (D-7: task descriptions are not notes),
 * so RichTextImageProcessor::process() is always called with
 * $allowMentions = false.
 */
final class TaskDescriptionWriter
{
    public function __construct(private readonly RichTextImageProcessor $images) {}

    /**
     * Sets $task->description from the raw submitted HTML on a brand-new
     * Task. $task MUST already be persisted (has an id): any inline
     * `data:` URI image becomes one of ITS OWN attachments. The caller
     * saves $task again if this leaves it dirty.
     */
    public function applyOnCreate(Task $task, ?string $rawHtml, User $creator): void
    {
        $task->description = $this->sanitizedOrNull($task, $rawHtml, $creator);
    }

    /**
     * Same as applyOnCreate() for an update that actually submitted
     * `description`, PLUS the D-4 cleanup: the Task's `rich_text` attachments
     * no longer referenced are deleted after the write transaction commits.
     * Call only from inside the same DB::transaction() as $task->save().
     */
    public function applyOnUpdate(Task $task, ?string $rawHtml, User $actor): void
    {
        $keepIds = [];

        if ($rawHtml !== null && ! RichTextPlainText::isEmpty($rawHtml)) {
            $result = $this->images->process($rawHtml, $task, $actor, false, 'description');
            $task->description = $result->html;
            $keepIds = $result->referencedAttachmentIds;
        } else {
            $task->description = null;
        }

        DB::afterCommit(fn () => $this->images->deleteUnreferenced($task, $keepIds));
    }

    private function sanitizedOrNull(Task $task, ?string $rawHtml, User $actor): ?string
    {
        if ($rawHtml === null || RichTextPlainText::isEmpty($rawHtml)) {
            return null;
        }

        return $this->images->process($rawHtml, $task, $actor, false, 'description')->html;
    }
}
