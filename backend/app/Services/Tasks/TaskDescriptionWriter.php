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
        [$html] = $this->process($task, $rawHtml, $creator);
        $task->description = $html;
    }

    /**
     * Same as applyOnCreate() for an update that actually submitted
     * `description`, PLUS the D-4 cleanup: the Task's `rich_text` attachments
     * no longer referenced are deleted after the write transaction commits.
     * Call only from inside the same DB::transaction() as $task->save().
     */
    public function applyOnUpdate(Task $task, ?string $rawHtml, User $actor): void
    {
        [$html, $keepIds] = $this->process($task, $rawHtml, $actor);
        $task->description = $html;

        DB::afterCommit(fn () => $this->images->deleteUnreferenced($task, $keepIds));
    }

    /**
     * Sanitizes/processes $rawHtml for $task (D-1..D-4). Two distinct inputs
     * both collapse to "empty" (D-2, null persisted): a $rawHtml that is
     * ALREADY blank/imageless, and one that only LOOKS non-blank — e.g. a
     * lone `<img src="https://remote">`, which carries no
     * `data-attachment-id` the sanitizer can trust and is stripped entirely —
     * so the result must be re-checked AFTER process(), not assumed from the
     * raw input alone.
     *
     * @return array{0: ?string, 1: array<int, int>} the html (or null) and
     *                                               the attachment ids still
     *                                               referenced by it
     */
    private function process(Task $task, ?string $rawHtml, User $actor): array
    {
        if ($rawHtml === null || RichTextPlainText::isEmpty($rawHtml)) {
            return [null, []];
        }

        $result = $this->images->process($rawHtml, $task, $actor, false, 'description');

        if (RichTextPlainText::isEmpty($result->html)) {
            return [null, []];
        }

        return [$result->html, $result->referencedAttachmentIds];
    }
}
