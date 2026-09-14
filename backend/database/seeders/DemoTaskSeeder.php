<?php

namespace Database\Seeders;

use App\DataObjects\Tasks\CreateTaskData;
use App\DataObjects\Tasks\UpdateTaskData;
use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Services\Tasks\TaskActionService;
use App\Services\Tasks\TaskWriteLock;
use App\Services\TaskService;
use Database\Seeders\Concerns\PicksTaskRecordLinks;
use Database\Seeders\DemoCatalog\DemoTaskCatalogue;
use DateTimeImmutable;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Development seed for the Tasks module (spec 0101/0116): a hierarchy of
 * activities plus their two membership pivots (`task_assignee`,
 * `task_watcher`), every row created through TaskService::create() — the same
 * path POST /api/tasks uses — so `creator_id`, the referente/anagrafica
 * coherence (AC-014) and the closing-feedback rule (D-7) all come from the
 * real write path, never a raw insert. The frozen Tasks go through
 * TaskActionService::block() for the same reason, acting as the Task's own
 * creator (the role that owns the mandate, spec 0116 D-2).
 *
 * The five classification lookups are NOT seeded here: they are the client's
 * reference vocabulary, owned by QualificaTaskTaxonomySeeder, which DemoDataSeeder
 * runs immediately before this step — an empty status pick-list makes this seeder
 * a no-op rather than an invented vocabulary. WHAT a Task may point at is loaded
 * by Concerns\PicksTaskRecordLinks; this class decides what a Task IS.
 *
 * Idempotent: clears its own table before reseeding (mirrors
 * DemoWorkOrderSeeder). Deletion goes through the model, one record at a time,
 * so HasAttachments' `deleting` hook runs; the notes thread has no such hook and
 * no FK, so it is force-deleted here explicitly — otherwise a re-run would leave
 * every previous note orphaned on a `notable_id` nothing points at.
 */
class DemoTaskSeeder extends Seeder
{
    use PicksTaskRecordLinks;

    private const int ROOT_TASKS = 40;

    private const int SUBTASKS = 20;

    private const int MAX_ASSIGNEES = 3;

    private const int MAX_WATCHERS = 2;

    /** Every Nth created Task is frozen, so the blocked state is visible in the demo. */
    private const int BLOCKED_STRIDE = 9;

    /** The estimates an operator actually picks, in minutes. */
    private const array ESTIMATED_MINUTES = [15, 30, 45, 60, 90, 120, 240, 480];

    /** How long a Task that carries an hour actually occupies, in minutes. */
    private const array SLOT_MINUTES = [30, 60, 90, 120];

    /** Working-day bounds of the seeded hours: a demo Task never starts at 03:29. */
    private const int FIRST_WORKING_HOUR = 8;

    private const int LAST_WORKING_HOUR = 17;

    /** Hours land on the quarter, the way an operator writes them. */
    private const array QUARTERS = [0, 15, 30, 45];

    /** Fixed so a re-run reproduces the same dataset. */
    private const int FAKER_SEED = 20260911;

    /**
     * The two phases D-4 (spec 0123) reserves to the domain actions: no
     * PATCH can reach them any more, so createTask() applies them with a
     * direct write on the model instead of TaskService::update() (D-11).
     *
     * @var array<int, TaskStatusGroup>
     */
    private const array ACTION_ONLY_GROUPS = [
        TaskStatusGroup::InValidation,
        TaskStatusGroup::ClosedPositive,
    ];

    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskActionService $actions,
    ) {}

    public function run(): void
    {
        // Step 1: drop the previous run, children before parents
        // (`parent_task_id` is restrictOnDelete).
        $this->clearExistingTasks();

        // Step 2: everything a Task may point at, drawn from one seeded Faker.
        $faker = FakerFactory::create('it_IT');
        $faker->seed(self::FAKER_SEED);
        $this->loadTaskContext($faker);

        if (! $this->hasTaskContext()) {
            return;
        }

        // Step 3: the root activities, then the breakdown hanging off them —
        // silently, since the real write path notifies every assignee/watcher.
        $this->withoutNotifications(fn () => $this->seedSubtasks($this->seedRoots()));
    }

    /**
     * TaskService notifies assignees and watchers by mail (spec 0119, voci 7
     * and 8): seeding 60 Tasks must not queue those mails. The previous
     * notification channel is restored afterwards, so a caller that faked it
     * (or a later seeder in the same process) keeps its own.
     */
    private function withoutNotifications(callable $seed): void
    {
        $notifications = Notification::getFacadeRoot();
        Notification::fake();

        try {
            $seed();
        } finally {
            Notification::swap($notifications);
        }
    }

    private function clearExistingTasks(): void
    {
        Task::query()->whereNotNull('parent_task_id')->orderBy('id')->get()
            ->each(fn (Task $task) => $this->deleteWithThread($task));

        Task::query()->orderBy('id')->get()
            ->each(fn (Task $task) => $this->deleteWithThread($task));
    }

    /**
     * The notes thread is a morph relation with no FK and no cleanup hook of
     * its own, so it goes first — force-deleted, since a soft-deleted note
     * would outlive the host it belongs to.
     */
    private function deleteWithThread(Task $task): void
    {
        $task->notesWithTrashed()->forceDelete();
        $task->delete();
    }

    /**
     * @return array<int, Task>
     */
    private function seedRoots(): array
    {
        $roots = [];

        for ($index = 0; $index < self::ROOT_TASKS; $index++) {
            $roots[] = $this->createTask($index, null);
        }

        return $roots;
    }

    /**
     * The breakdown: each sub-task inherits its parent's record links — a step
     * of an activity belongs to the same anagrafica/opportunita' as the
     * activity itself — and carries its own status, classification and team.
     * A LOCKED root — blocked, or drawn into a closing phase — is excluded
     * from the pick (spec 0123, D-9): its chain is frozen, so
     * TaskWriteLock::assertParentChainUnlocked() would refuse the insert —
     * the demo dataset simply never proposes it as a parent.
     *
     * @param  array<int, Task>  $roots
     */
    private function seedSubtasks(array $roots): void
    {
        $availableRoots = array_values(array_filter($roots, static fn (Task $root): bool => ! TaskWriteLock::isLocked($root)));

        for ($index = 0; $index < self::SUBTASKS; $index++) {
            $this->createTask($index, $availableRoots[$index % count($availableRoots)]);
        }
    }

    /**
     * Every Task travels through the real write path. The initial status is
     * derived server-side (spec 0118 D-3), so the drawn one is applied
     * afterwards through the same PATCH path, acting as the creator — the
     * role that owns the mandate (spec 0116 D-2, spec 0121 D-1). Every Nth
     * Task is then frozen through the domain action. D-11 (spec 0123): a
     * drawn status in `in_validation`/`closed_positive` can no longer be
     * reached by that PATCH path (D-4), so those two phases are applied with
     * a direct write on the model instead — a demo dataset, no notification
     * to send either way, and `completion_date` is already coherent since
     * Step 2 derived it from the drawn (final) status, not the created one.
     */
    private function createTask(int $index, ?Task $parent): Task
    {
        // Step 1: who creates it, and which phase it is shown in. A sub-task is
        // created by its parent's creator: spec 0125 D-4 only lets an actor who
        // may edit the parent hang a child under it, and a random user rarely
        // can. A sub-task of a parent that has not started yet cannot already
        // be finished inside the parent's range, so it is never drawn closing.
        $status = $this->faker->randomElement($this->statusesFor($parent)->all());
        $creator = $parent === null
            ? $this->faker->randomElement($this->users->all())
            : $this->users->firstWhere('id', $parent->creator_id);
        $data = $parent === null
            ? $this->buildRootData($status, $creator->id)
            : $this->buildSubtaskData($parent, $status, $creator->id);

        // Step 2: create through the real write path.
        $task = $this->tasks->create($data, $creator);

        // Step 3: move it to the drawn status.
        if ($task->task_status_id !== $status->id) {
            $task = in_array($status->group, self::ACTION_ONLY_GROUPS, true)
                ? $this->applyActionOnlyStatus($task, $status)
                : $this->tasks->update($task, new UpdateTaskData(taskStatusId: $status->id), $creator);
        }

        // Step 4: freeze every Nth one.
        if ($index % self::BLOCKED_STRIDE === self::BLOCKED_STRIDE - 1) {
            $task = $this->actions->block($task, $creator);
        }

        return $task;
    }

    /**
     * @return Collection<int, TaskStatus>
     */
    private function statusesFor(?Task $parent): Collection
    {
        if ($parent === null || ! $parent->start_date->isFuture()) {
            return $this->statuses;
        }

        return $this->statuses->reject(static fn (TaskStatus $status): bool => $status->isClosing())->values();
    }

    /**
     * D-11 (spec 0123): a direct model write, bypassing TaskService::update()
     * and its D-4 guard — the seed IS the record, not a client PATCHing it.
     * No notification (the whole seed runs inside withoutNotifications()
     * already); the same loadDetail() the real write path returns, so the
     * caller sees the same shape either branch of Step 3 takes.
     */
    private function applyActionOnlyStatus(Task $task, TaskStatus $status): Task
    {
        $task->task_status_id = $status->id;
        $task->save();

        return $this->tasks->loadDetail($task);
    }

    private function buildRootData(TaskStatus $status, int $creatorId): CreateTaskData
    {
        $opportunityId = $this->pickOptional(array_keys($this->registryByOpportunity), 0.3);
        // An opportunity already names its anagrafica: reuse it rather than
        // pairing the Task with an unrelated one.
        $registryId = $opportunityId !== null
            ? $this->registryByOpportunity[$opportunityId]
            : $this->pickOptional(array_keys($this->registryNames), 0.7);

        $subject = $registryId !== null ? $this->registryNames[$registryId] : $this->faker->company();

        return $this->buildData(
            title: sprintf($this->faker->randomElement(DemoTaskCatalogue::TITLE_TEMPLATES), $subject),
            registryId: $registryId,
            referentId: $this->pickReferent($registryId),
            opportunityId: $opportunityId,
            workOrderId: $this->pickOptional($this->workOrderIds, 0.2),
            parentTaskId: null,
            status: $status,
            creatorId: $creatorId,
        );
    }

    private function buildSubtaskData(Task $parent, TaskStatus $status, int $creatorId): CreateTaskData
    {
        return $this->buildData(
            title: $this->faker->randomElement(DemoTaskCatalogue::SUBTASK_TITLES),
            registryId: $parent->registry_id,
            referentId: $parent->referent_id,
            opportunityId: $parent->opportunity_id,
            workOrderId: $parent->work_order_id,
            parentTaskId: $parent->id,
            status: $status,
            creatorId: $creatorId,
            parent: $parent,
        );
    }

    /**
     * The shared payload shape of both kinds of Task. `isBlocked` is absent by
     * construction (spec 0116 D-6: a Task is created unblocked and frozen
     * afterwards through the action), and so are `creatorId` (D-10: it is the
     * actor, never payload) and `taskStatusId` (spec 0118 D-3: derived). The
     * drawn $status still shapes the dates and the feedback, so the row is
     * coherent once createTask() applies it. $parent is passed through so
     * buildDates() can keep a sub-task's dates inside its parent's range
     * (spec 0123, D-7) — TaskParentDateRangeGuard runs on the real write
     * path this seeder uses, so a date drawn independently of the parent
     * would 422 rather than seed.
     */
    private function buildData(
        string $title,
        ?int $registryId,
        ?int $referentId,
        ?int $opportunityId,
        ?int $workOrderId,
        ?int $parentTaskId,
        TaskStatus $status,
        int $creatorId,
        ?Task $parent = null,
    ): CreateTaskData {
        $dates = $this->buildDates($status, $parent);
        $requiresClosureFeedback = $this->faker->boolean(30);
        $assigneeIds = $this->faker->randomElements(
            $this->userIds,
            $this->faker->numberBetween(1, min(self::MAX_ASSIGNEES, count($this->userIds))),
        );
        // spec 0118 D-1: the requester is mandatory.
        $requesterId = $this->faker->randomElement($this->userIds);
        $startTime = $this->pickStartTime();

        return new CreateTaskData(
            title: $title,
            requesterId: $requesterId,
            endDate: $dates['end'],
            assigneeIds: $assigneeIds,
            description: $this->faker->optional(0.7)->sentence(12),
            registryId: $registryId,
            referentId: $referentId,
            parentTaskId: $parentTaskId,
            taskTypeId: $this->pickOptional($this->lookupIds[TaskType::class], 0.8),
            taskPriorityId: $this->pickOptional($this->lookupIds[TaskPriority::class], 0.8),
            taskImportanceId: $this->pickOptional($this->lookupIds[TaskImportance::class], 0.8),
            taskCategoryId: $this->pickOptional($this->lookupIds[TaskCategory::class], 0.8),
            opportunityId: $opportunityId,
            workOrderId: $workOrderId,
            startDate: $dates['start'],
            completionDate: $dates['completion'],
            startTime: $startTime,
            endTime: $startTime === null ? null : $this->endTimeFor($startTime),
            estimatedMinutes: $this->pickOptional(self::ESTIMATED_MINUTES, 0.5),
            requiresClosureFeedback: $requiresClosureFeedback,
            // D-7: a closing status with the flag on demands a non-empty
            // feedback, so the status decides whether this key is optional.
            closureFeedback: $requiresClosureFeedback && $status->isClosing()
                ? $this->faker->sentence(10)
                : $this->faker->optional(0.2)->sentence(8),
            watcherIds: $this->pickWatchers([...$assigneeIds, $creatorId, $requesterId], self::MAX_WATCHERS),
        );
    }

    /**
     * A Task in a CLOSING phase carries the day it was completed; one still
     * open never does. Finished work is dated in the PAST, window included:
     * drawing a closed Task's completion from a start date that may be a month
     * out would date finished work in the future. An open Task keeps the wider
     * window — it legitimately covers planned activities.
     *
     * A sub-task additionally stays inside its parent's [start_date, end_date]
     * (spec 0123, D-7): both dates are drawn from that window instead of the
     * usual rolling one, since a root task's own buildDates() call always
     * leaves it with concrete (non-null) bounds.
     *
     * @return array{start: string, end: string, completion: string|null}
     */
    private function buildDates(TaskStatus $status, ?Task $parent = null): array
    {
        $isClosed = $status->isClosing();

        if ($parent !== null) {
            // Finished work starts no later than today (statusesFor() already
            // excludes closing phases under a parent starting in the future).
            $startWindowEnd = $isClosed ? min($parent->end_date, now()->startOfDay()) : $parent->end_date;
            $start = DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween($parent->start_date, $startWindowEnd));
            $end = DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween($start->format('Y-m-d'), $parent->end_date));

            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'completion' => $isClosed ? min($end, new DateTimeImmutable('today'))->format('Y-m-d') : null,
            ];
        }

        $start = DateTimeImmutable::createFromMutable(
            $this->faker->dateTimeBetween('-3 months', $isClosed ? '-1 week' : '+1 month'),
        );
        $end = $start->modify(sprintf('+%d days', $this->faker->numberBetween(1, 20)));

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'completion' => $isClosed ? min($end, new DateTimeImmutable('today'))->format('Y-m-d') : null,
        ];
    }

    /**
     * An hour on the quarter inside the working day, or none: `start_time` is
     * nullable and most Tasks are dated to the day alone.
     */
    private function pickStartTime(): ?string
    {
        if (! $this->faker->boolean(30)) {
            return null;
        }

        return sprintf(
            '%02d:%02d',
            $this->faker->numberBetween(self::FIRST_WORKING_HOUR, self::LAST_WORKING_HOUR),
            $this->faker->randomElement(self::QUARTERS),
        );
    }

    /**
     * The slot closes the same day it opens: LAST_WORKING_HOUR plus the longest
     * SLOT_MINUTES never crosses midnight, which would leave `end_time` reading
     * as earlier than `start_time`.
     */
    private function endTimeFor(string $startTime): string
    {
        return (new DateTimeImmutable($startTime))
            ->modify(sprintf('+%d minutes', $this->faker->randomElement(self::SLOT_MINUTES)))
            ->format('H:i');
    }
}
