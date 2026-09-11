<?php

namespace Database\Seeders;

use App\DataObjects\Tasks\CreateTaskData;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Services\Tasks\TaskActionService;
use App\Services\TaskService;
use Database\Seeders\Concerns\PicksTaskRecordLinks;
use Database\Seeders\DemoCatalog\DemoTaskCatalogue;
use DateTimeImmutable;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;

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

        // Step 3: the root activities, then the breakdown hanging off them.
        $this->seedSubtasks($this->seedRoots());
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
            $roots[] = $this->createTask($this->buildRootData(), $index);
        }

        return $roots;
    }

    /**
     * The breakdown: each sub-task inherits its parent's record links — a step
     * of an activity belongs to the same anagrafica/opportunita' as the
     * activity itself — and carries its own status, classification and team.
     *
     * @param  array<int, Task>  $roots
     */
    private function seedSubtasks(array $roots): void
    {
        for ($index = 0; $index < self::SUBTASKS; $index++) {
            $this->createTask($this->buildSubtaskData($roots[$index % count($roots)]), $index);
        }
    }

    /**
     * Every Task travels through the real write path; every Nth one is then
     * frozen through the domain action, acting as its own creator.
     */
    private function createTask(CreateTaskData $data, int $index): Task
    {
        $task = $this->tasks->create($data, $this->faker->randomElement($this->users->all()));

        if ($index % self::BLOCKED_STRIDE === self::BLOCKED_STRIDE - 1) {
            $this->actions->block($task, $task->creator);
        }

        return $task;
    }

    private function buildRootData(): CreateTaskData
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
        );
    }

    private function buildSubtaskData(Task $parent): CreateTaskData
    {
        return $this->buildData(
            title: $this->faker->randomElement(DemoTaskCatalogue::SUBTASK_TITLES),
            registryId: $parent->registry_id,
            referentId: $parent->referent_id,
            opportunityId: $parent->opportunity_id,
            workOrderId: $parent->work_order_id,
            parentTaskId: $parent->id,
        );
    }

    /**
     * The shared payload shape of both kinds of Task. `isBlocked` is absent by
     * construction (spec 0116 D-6: a Task is created unblocked and frozen
     * afterwards through the action) and so is `creatorId` (D-10: it is the
     * actor, never payload).
     */
    private function buildData(
        string $title,
        ?int $registryId,
        ?int $referentId,
        ?int $opportunityId,
        ?int $workOrderId,
        ?int $parentTaskId,
    ): CreateTaskData {
        $status = $this->faker->randomElement($this->statuses->all());
        $dates = $this->buildDates($status);
        $requiresClosureFeedback = $this->faker->boolean(30);
        $assigneeIds = $this->faker->randomElements(
            $this->userIds,
            $this->faker->numberBetween(1, min(self::MAX_ASSIGNEES, count($this->userIds))),
        );
        $startTime = $this->pickStartTime();

        return new CreateTaskData(
            title: $title,
            taskStatusId: $status->id,
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
            requesterId: $this->pickOptional($this->userIds, 0.4),
            startDate: $dates['start'],
            endDate: $dates['end'],
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
            assigneeIds: $assigneeIds,
            watcherIds: $this->pickWatchers($assigneeIds, self::MAX_WATCHERS),
        );
    }

    /**
     * A Task in a CLOSING phase carries the day it was completed; one still
     * open never does. Finished work is dated in the PAST, window included:
     * drawing a closed Task's completion from a start date that may be a month
     * out would date finished work in the future. An open Task keeps the wider
     * window — it legitimately covers planned activities.
     *
     * @return array{start: string, end: string, completion: string|null}
     */
    private function buildDates(TaskStatus $status): array
    {
        $isClosed = $status->isClosing();
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
