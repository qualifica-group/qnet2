<?php

declare(strict_types=1);

namespace App\Services\SupportRequests;

use App\DataObjects\SupportRequests\SupportRequestData;
use App\DataObjects\Tasks\CreateTaskData;
use App\Models\Attachment;
use App\Models\SupportTopic;
use App\Models\Task;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a user's IT support request into a Task (spec 0334). Everything the
 * user does not choose (type, category, priority, importance, assignees,
 * watchers, deadline) comes from the topic's configuration; the Task is
 * created ONLY through TaskService::create, in one transaction with the
 * attachments.
 */
class SupportRequestService
{
    /** Length of `tasks.title`. */
    private const int TITLE_MAX = 191;

    /** Attachment collection of the Task "Documenti" section. */
    private const string ATTACHMENT_COLLECTION = 'documents';

    public function __construct(
        private readonly TaskService $tasks,
        private readonly AttachmentService $attachments,
    ) {}

    public function handle(User $actor, SupportRequestData $data): Task
    {
        // Step 1: the topic and its active assignees (a topic nobody can take is refused).
        $topic = SupportTopic::query()->findOrFail($data->supportTopicId);
        $assigneeIds = $this->activeAssigneeIds($topic);

        // Step 2: the Task payload from the topic's configuration.
        $taskData = $this->buildTaskData($actor, $topic, $assigneeIds, $data);

        // Step 3: Task + attachments in one transaction; stored binaries are
        // removed if anything fails, since a rollback cannot delete files.
        $stored = [];

        try {
            return DB::transaction(function () use ($actor, $taskData, $data, &$stored): Task {
                $task = $this->tasks->create($taskData, $actor);

                foreach ($data->attachments as $file) {
                    $stored[] = $this->attachments->storeFor($task, $file, self::ATTACHMENT_COLLECTION);
                }

                return $task;
            });
        } catch (Throwable $exception) {
            $this->deleteBinaries($stored);

            throw $exception;
        }
    }

    /**
     * @return array<int, int>
     */
    private function activeAssigneeIds(SupportTopic $topic): array
    {
        $ids = $topic->assignees()->where('users.is_active', true)->pluck('users.id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'support_topic_id' => [__('This support topic has no active assignee.')],
            ]);
        }

        return $ids;
    }

    /**
     * @param  array<int, int>  $assigneeIds
     */
    private function buildTaskData(User $actor, SupportTopic $topic, array $assigneeIds, SupportRequestData $data): CreateTaskData
    {
        $urgent = $data->isUrgent;
        $dueDays = $urgent && $topic->urgent_due_days !== null ? $topic->urgent_due_days : $topic->due_days;
        $priorityId = $urgent && $topic->urgent_task_priority_id !== null
            ? $topic->urgent_task_priority_id
            : $topic->task_priority_id;

        // The requester can never be an observer of the same Task.
        $watcherIds = array_values(array_diff(
            $topic->watchers()->pluck('users.id')->map(fn ($id): int => (int) $id)->all(),
            [$actor->id],
            $assigneeIds,
        ));

        return new CreateTaskData(
            title: $this->title($topic, $data->description),
            requesterId: $actor->id,
            endDate: now()->addDays($dueDays)->toDateString(),
            assigneeIds: $assigneeIds,
            description: $this->descriptionHtml($data),
            taskTypeId: $topic->task_type_id,
            taskPriorityId: $priorityId,
            taskImportanceId: $topic->task_importance_id,
            taskCategoryId: $topic->task_category_id,
            requiresClosureFeedback: $topic->requires_closure_feedback,
            watcherIds: $watcherIds,
            isPrivate: $topic->is_private,
            notifyAssignedUsers: true,
        );
    }

    /**
     * "{topic name}: {start of the description on one line}", capped to the column.
     */
    private function title(SupportTopic $topic, string $description): string
    {
        $oneLine = trim((string) preg_replace('/\s+/u', ' ', $description));

        return Str::limit($topic->name.': '.$oneLine, self::TITLE_MAX, '');
    }

    /**
     * User text is plain text (never HTML): escaped, newlines as <br>, then
     * the page/browser context paragraph, also escaped.
     */
    private function descriptionHtml(SupportRequestData $data): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $data->description);
        $html = '<p>'.nl2br(e($text), false).'</p>';

        $context = array_filter([
            $data->pageUrl !== null ? __('Page').': '.e($data->pageUrl) : null,
            $data->userAgent !== null ? __('Browser').': '.e($data->userAgent) : null,
        ]);

        return $context === [] ? $html : $html.'<p>'.implode('<br>', $context).'</p>';
    }

    /**
     * @param  array<int, Attachment>  $stored
     */
    private function deleteBinaries(array $stored): void
    {
        foreach ($stored as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }
}
