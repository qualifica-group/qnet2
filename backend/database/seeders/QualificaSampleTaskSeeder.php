<?php

namespace Database\Seeders;

use App\DataObjects\Tasks\CompleteTaskData;
use App\DataObjects\Tasks\CreateTaskData;
use App\DataObjects\Tasks\UpdateTaskData;
use App\DataObjects\TimeEntries\TimeEntryData;
use App\Enums\TaskStatusGroup;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskCompletionService;
use App\Services\TaskService;
use Database\Seeders\Concerns\ResolvesSeedActor;
use Database\Seeders\Concerns\SeedsWithoutNotifications;
use Database\Seeders\DemoCatalog\DemoTaskCatalogue;
use DateTimeImmutable;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The Task step of the sample dataset (user directive 2026-09-24): activities
 * hanging on the batch's Commesse and Opportunita', every row created through
 * TaskService::create() — the path POST /api/tasks uses — so the derived
 * initial status, the record-link coherence and the watcher overlap rule all
 * come from the real write path.
 *
 * A Task points at a commessa OR an opportunity, never both (spec 0154,
 * D-11), and always carries the anagrafica of the record it points at. On a
 * commessa the team is its supervisor; on an opportunity, its Gestori Account.
 *
 * Status, by the system's own paths: some stay on the derived birth status,
 * some move to a custom pending row or close negatively through the PATCH path
 * (acting as the creator, who owns the mandate), some are COMPLETED through
 * TaskCompletionService::complete() by their assignee — which, by rule (spec
 * 0127 D-1/D-2), writes the Task's segnatempo in the same transaction. The two
 * action-only phases (spec 0123, D-4) are never forced with a direct write.
 *
 * Mails are suppressed (SeedsWithoutNotifications): the write path notifies
 * every assignee. `$sinceOpportunityId` confines the step to the running
 * chain's own batch.
 */
class QualificaSampleTaskSeeder extends Seeder
{
    use ResolvesSeedActor;
    use SeedsWithoutNotifications;

    /** The batch size when the caller names none (`--tasks` of qualifica:seed-sample). */
    public const int DEFAULT_TASKS = 30;

    private const string OUTCOME_OPEN = 'open';

    private const string OUTCOME_PENDING = 'pending';

    private const string OUTCOME_COMPLETED = 'completed';

    private const string OUTCOME_CLOSED_NEGATIVE = 'closed_negative';

    /** Rotated over the seeded Tasks, one outcome each. */
    private const array OUTCOMES = [
        self::OUTCOME_OPEN,
        self::OUTCOME_COMPLETED,
        self::OUTCOME_PENDING,
        self::OUTCOME_OPEN,
        self::OUTCOME_COMPLETED,
        self::OUTCOME_CLOSED_NEGATIVE,
    ];

    /** One Task in WORK_ORDER_STRIDE hangs on a commessa, when the batch has any. */
    private const int WORK_ORDER_STRIDE = 3;

    private const int MAX_OPPORTUNITY_ASSIGNEES = 2;

    /** The estimates an operator actually picks, in minutes. */
    private const array ESTIMATED_MINUTES = [30, 60, 90, 120, 240];

    /** The minutes the completion segnatempo records. */
    private const array WORKED_MINUTES = [30, 45, 60, 90, 120];

    private const int MAX_DURATION_DAYS = 14;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskCompletionService $completion,
    ) {}

    public function run(int $tasks = self::DEFAULT_TASKS, int $sinceOpportunityId = 0): void
    {
        // Step 1: the vocabulary and the records a Task may point at.
        $actor = $this->resolveActor();
        $taskTypes = TaskType::query()->where('is_active', true)->orderBy('id')->get();
        $workOrders = $this->batchWorkOrders($sinceOpportunityId);
        $opportunities = Opportunity::query()
            ->where('id', '>', $sinceOpportunityId)
            ->with('managers')
            ->orderBy('id')
            ->get();

        if ($actor === null || $taskTypes->isEmpty() || $opportunities->isEmpty()) {
            $this->command?->warn('Sample tasks skipped: no user, no active task type, or no opportunity in this batch.');

            return;
        }

        $statuses = TaskStatus::query()->where('is_active', true)->orderBy('sort_order')->get();
        $faker = FakerFactory::create('it_IT');

        // Step 2: the batch, with the write path's mails suppressed.
        $this->withoutNotifications(function () use ($tasks, $faker, $actor, $taskTypes, $workOrders, $opportunities, $statuses): void {
            for ($index = 0; $index < $tasks; $index++) {
                $outcome = self::OUTCOMES[$index % count(self::OUTCOMES)];
                $task = $index % self::WORK_ORDER_STRIDE === 0 && $workOrders->isNotEmpty()
                    ? $this->createOnWorkOrder($faker, $workOrders[intdiv($index, self::WORK_ORDER_STRIDE) % $workOrders->count()], $taskTypes, $outcome)
                    : $this->createOnOpportunity($faker, $opportunities[$index % $opportunities->count()], $actor, $taskTypes, $outcome);

                // Step 3: move it along its outcome.
                $this->applyOutcome($faker, $task, $outcome, $statuses, $taskTypes);
            }
        });

        $this->command?->info(sprintf('%d sample tasks seeded.', $tasks));
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    private function batchWorkOrders(int $sinceOpportunityId): Collection
    {
        return WorkOrder::query()
            ->whereHas('quote', static fn ($query) => $query->where('opportunity_id', '>', $sinceOpportunityId))
            ->with(['supervisors', 'quote.opportunity'])
            ->orderBy('id')
            ->get()
            ->filter(static fn (WorkOrder $workOrder): bool => $workOrder->supervisors->isNotEmpty())
            ->values();
    }

    /**
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function createOnWorkOrder(Generator $faker, WorkOrder $workOrder, Collection $taskTypes, string $outcome): Task
    {
        $supervisor = $workOrder->supervisors->first();

        return $this->tasks->create($this->buildData(
            faker: $faker,
            title: sprintf('%s - %s', $faker->randomElement(DemoTaskCatalogue::SUBTASK_TITLES), $workOrder->code),
            requesterId: $supervisor->id,
            assigneeIds: [$supervisor->id],
            registryId: $workOrder->quote->opportunity->registry_id,
            opportunityId: null,
            workOrderId: $workOrder->id,
            taskTypes: $taskTypes,
            outcome: $outcome,
        ), $supervisor);
    }

    /**
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function createOnOpportunity(Generator $faker, Opportunity $opportunity, User $actor, Collection $taskTypes, string $outcome): Task
    {
        $assigneeIds = $opportunity->managers->pluck('id')->take(self::MAX_OPPORTUNITY_ASSIGNEES)->all();

        return $this->tasks->create($this->buildData(
            faker: $faker,
            title: sprintf($faker->randomElement(DemoTaskCatalogue::TITLE_TEMPLATES), $opportunity->name),
            requesterId: $actor->id,
            assigneeIds: $assigneeIds === [] ? [$actor->id] : $assigneeIds,
            registryId: $opportunity->registry_id,
            opportunityId: $opportunity->id,
            workOrderId: null,
            taskTypes: $taskTypes,
            outcome: $outcome,
        ), $actor);
    }

    /**
     * Finished work is dated in the past; open work may be planned ahead.
     *
     * @param  array<int, int>  $assigneeIds
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function buildData(
        Generator $faker,
        string $title,
        int $requesterId,
        array $assigneeIds,
        int $registryId,
        ?int $opportunityId,
        ?int $workOrderId,
        Collection $taskTypes,
        string $outcome,
    ): CreateTaskData {
        $isFinished = in_array($outcome, [self::OUTCOME_COMPLETED, self::OUTCOME_CLOSED_NEGATIVE], true);
        $start = DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 month', $isFinished ? '-3 days' : '+2 weeks'));
        $end = $start->modify(sprintf('+%d days', $faker->numberBetween(1, self::MAX_DURATION_DAYS)));

        return new CreateTaskData(
            title: $title,
            requesterId: $requesterId,
            endDate: $end->format('Y-m-d'),
            assigneeIds: $assigneeIds,
            description: $faker->boolean(60) ? $faker->sentence(12) : null,
            registryId: $registryId,
            taskTypeId: $taskTypes->random()->id,
            opportunityId: $opportunityId,
            workOrderId: $workOrderId,
            startDate: $start->format('Y-m-d'),
            estimatedMinutes: $faker->randomElement(self::ESTIMATED_MINUTES),
        );
    }

    /**
     * @param  Collection<int, TaskStatus>  $statuses
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function applyOutcome(Generator $faker, Task $task, string $outcome, Collection $statuses, Collection $taskTypes): void
    {
        match ($outcome) {
            self::OUTCOME_OPEN => null,
            self::OUTCOME_COMPLETED => $this->complete($faker, $task, $taskTypes),
            self::OUTCOME_PENDING => $this->moveTo($task, $statuses->first(
                static fn (TaskStatus $status): bool => $status->group === TaskStatusGroup::Pending,
            )),
            self::OUTCOME_CLOSED_NEGATIVE => $this->moveTo($task, $statuses->first(
                static fn (TaskStatus $status): bool => $status->group === TaskStatusGroup::ClosedNegative,
            )),
        };
    }

    /**
     * "Completa", by the Task's first assignee: with `requires_validation`
     * off the Task closes positively, and the action writes its segnatempo.
     *
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function complete(Generator $faker, Task $task, Collection $taskTypes): void
    {
        $assignee = $task->assignees->first();

        $this->completion->complete($task, new CompleteTaskData(
            timeEntry: new TimeEntryData(
                date: now()->toDateString(),
                taskTypeId: $task->task_type_id ?? $taskTypes->random()->id,
                minutes: $faker->randomElement(self::WORKED_MINUTES),
                notes: $faker->boolean(40) ? $faker->sentence(8) : null,
                taskId: $task->id,
            ),
        ), $assignee);
    }

    /**
     * The PATCH path, acting as the creator (spec 0116 D-2).
     */
    private function moveTo(Task $task, ?TaskStatus $status): void
    {
        if ($status === null) {
            return;
        }

        $this->tasks->update($task, new UpdateTaskData(taskStatusId: $status->id), $task->creator);
    }
}
