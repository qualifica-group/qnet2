<?php

namespace Database\Seeders;

use App\DataObjects\TimeEntries\TimeEntryData;
use App\Enums\TaskStatusGroup;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TimeEntries\TimeEntryService;
use Database\Seeders\Concerns\ResolvesSeedActor;
use DateTimeImmutable;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The Segnatempo step of the sample dataset (user directive 2026-09-24): hours
 * logged over the last working days on the batch's records, every row created
 * through TimeEntryService::create() — the path POST /api/time-entries uses —
 * so the link rule (spec 0122, D-5) is the real one: an entry on a Task takes
 * its title and record links FROM the Task, and is written for one of its
 * assignees (the owner must see the Task); an entry without a Task points at
 * a commessa OR an opportunity, never both, and inherits its client. A note on
 * a commessa entry is mirrored as a comment on the commessa, as in the app.
 *
 * On top of these, every Task QualificaSampleTaskSeeder completed already
 * carries the segnatempo the completion action writes (spec 0127): this step
 * adds the day-to-day hours, not those.
 *
 * `$sinceOpportunityId` confines the step to the running chain's own batch.
 */
class QualificaSampleTimeEntrySeeder extends Seeder
{
    use ResolvesSeedActor;

    /** The batch size when the caller names none (`--time-entries` of qualifica:seed-sample). */
    public const int DEFAULT_TIME_ENTRIES = 60;

    /** How many past weeks (the current one included) the entries spread over. */
    private const int WEEKS = 2;

    /** Two entries in three go on an open Task, when the batch has any. */
    private const int STANDALONE_STRIDE = 3;

    private const array MINUTES = [15, 30, 45, 60, 90, 120, 180];

    private const array TITLES = [
        'Chiamata cliente', 'Redazione documentazione', 'Riunione di allineamento',
        'Analisi requisiti', 'Supporto operativo', 'Verifica pratiche',
    ];

    public function __construct(private readonly TimeEntryService $timeEntries) {}

    public function run(int $timeEntries = self::DEFAULT_TIME_ENTRIES, int $sinceOpportunityId = 0): void
    {
        // Step 1: the vocabulary and the records an entry may point at.
        $actor = $this->resolveActor();
        $taskTypeIds = TaskType::query()->where('is_active', true)->orderBy('id')->pluck('id')->all();
        $tasks = $this->openBatchTasks($sinceOpportunityId);
        $workOrders = WorkOrder::query()
            ->whereHas('quote', static fn ($query) => $query->where('opportunity_id', '>', $sinceOpportunityId))
            ->with('supervisors')
            ->orderBy('id')
            ->get();
        $opportunities = Opportunity::query()
            ->where('id', '>', $sinceOpportunityId)
            ->with('managers')
            ->orderBy('id')
            ->get();

        if ($actor === null || $taskTypeIds === [] || $opportunities->isEmpty()) {
            $this->command?->warn('Sample time entries skipped: no user, no active task type, or no opportunity in this batch.');

            return;
        }

        $faker = FakerFactory::create('it_IT');
        $days = $this->weekdays();

        // Step 2: the batch — on an open Task when there is one, otherwise on
        // a commessa or an opportunity.
        for ($index = 0; $index < $timeEntries; $index++) {
            [$data, $owner] = $tasks->isNotEmpty() && $index % self::STANDALONE_STRIDE !== 0
                ? $this->onTask($tasks[$index % $tasks->count()])
                : $this->standalone($faker, $index, $workOrders, $opportunities, $actor);

            $this->timeEntries->create(new TimeEntryData(
                date: $faker->randomElement($days),
                taskTypeId: $faker->randomElement($taskTypeIds),
                minutes: $faker->randomElement(self::MINUTES),
                title: $data['title'],
                notes: $faker->boolean(30) ? $faker->sentence(8) : null,
                opportunityId: $data['opportunity_id'],
                workOrderId: $data['work_order_id'],
                taskId: $data['task_id'],
            ), $owner);
        }

        $this->command?->info(sprintf('%d sample time entries seeded.', $timeEntries));
    }

    /**
     * The batch's Tasks still in progress, i.e. on an open or pending phase.
     *
     * @return Collection<int, Task>
     */
    private function openBatchTasks(int $sinceOpportunityId): Collection
    {
        return Task::query()
            ->where(static fn ($query) => $query
                ->where('opportunity_id', '>', $sinceOpportunityId)
                ->orWhereHas('workOrder.quote', static fn ($scoped) => $scoped->where('opportunity_id', '>', $sinceOpportunityId)))
            ->whereHas('taskStatus', static fn ($query) => $query->whereIn('group', [
                TaskStatusGroup::Open->value,
                TaskStatusGroup::Pending->value,
            ]))
            ->has('assignees')
            ->with(['assignees', 'watchers'])
            ->orderBy('id')
            ->get();
    }

    /**
     * D-5: the title and the record links come from the Task itself.
     *
     * @return array{0: array{title: string|null, opportunity_id: int|null, work_order_id: int|null, task_id: int|null}, 1: User}
     */
    private function onTask(Task $task): array
    {
        return [
            ['title' => null, 'opportunity_id' => null, 'work_order_id' => null, 'task_id' => $task->id],
            $task->assignees->first(),
        ];
    }

    /**
     * Alternates commessa (written by its supervisor) and opportunity (by
     * one of its Gestori Account), never both on one entry.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     * @param  Collection<int, Opportunity>  $opportunities
     * @return array{0: array{title: string|null, opportunity_id: int|null, work_order_id: int|null, task_id: int|null}, 1: User}
     */
    private function standalone(Generator $faker, int $index, Collection $workOrders, Collection $opportunities, User $actor): array
    {
        $title = $faker->randomElement(self::TITLES);

        if ($workOrders->isNotEmpty() && $index % 2 === 0) {
            $workOrder = $workOrders[$index % $workOrders->count()];

            return [
                ['title' => $title, 'opportunity_id' => null, 'work_order_id' => $workOrder->id, 'task_id' => null],
                $workOrder->supervisors->first() ?? $actor,
            ];
        }

        $opportunity = $opportunities[$index % $opportunities->count()];

        return [
            ['title' => $title, 'opportunity_id' => $opportunity->id, 'work_order_id' => null, 'task_id' => null],
            $opportunity->managers->first() ?? $actor,
        ];
    }

    /**
     * Monday-to-Friday dates of the last WEEKS weeks, today included.
     *
     * @return array<int, string>
     */
    private function weekdays(): array
    {
        $today = new DateTimeImmutable('today');
        $day = $today->modify('monday this week')->modify(sprintf('-%d weeks', self::WEEKS - 1));
        $dates = [];

        for (; $day <= $today; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $dates[] = $day->format('Y-m-d');
            }
        }

        return $dates === [] ? [$today->format('Y-m-d')] : $dates;
    }
}
