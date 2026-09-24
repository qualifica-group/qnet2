<?php

use App\Enums\TaskStatusGroup;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\QualificaSampleContractSeeder;
use Database\Seeders\QualificaSampleOpportunitySeeder;
use Database\Seeders\QualificaSampleQuoteSeeder;
use Database\Seeders\QualificaSampleTaskSeeder;
use Database\Seeders\QualificaSampleTimeEntrySeeder;
use Database\Seeders\QualificaSampleWorkOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Steps 7-8 of the sample dataset (user directive 2026-09-24): Task on the
// batch's Commesse and Opportunita', and Segnatempo on top — both through the
// system's own write paths and link rules.
uses(RefreshDatabase::class);

/** Standalone opportunities the activity steps hang on. */
const ACTIVITY_OPPORTUNITIES = 8;

/**
 * The deal flow up to the Commesse (steps 2, 4, 5, 6) on one offer category,
 * plus an active task type.
 */
function seedActivityAnchors(): void
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->count(2)->create(['category_id' => $category->getKey()]);
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(ACTIVITY_OPPORTUNITIES)->create();
    TaskType::factory()->create();

    app(QualificaSampleOpportunitySeeder::class)->run(opportunities: ACTIVITY_OPPORTUNITIES);
    test()->seed(QualificaSampleQuoteSeeder::class);
    app(QualificaSampleContractSeeder::class)->run(contracts: ACTIVITY_OPPORTUNITIES);
    test()->seed(QualificaSampleWorkOrderSeeder::class);
}

describe('QualificaSampleTaskSeeder', function (): void {
    it('hangs every Task on a commessa or an opportunity, never both, with its anagrafica', function (): void {
        seedActivityAnchors();

        app(QualificaSampleTaskSeeder::class)->run(tasks: 12);

        $tasks = Task::query()->with(['opportunity', 'workOrder.quote.opportunity', 'assignees'])->get();

        expect($tasks)->toHaveCount(12)
            ->and($tasks->filter(fn (Task $task): bool => ($task->opportunity_id === null) === ($task->work_order_id === null)))->toBeEmpty()
            ->and($tasks->whereNotNull('work_order_id'))->not->toBeEmpty()
            ->and($tasks->filter(fn (Task $task): bool => $task->registry_id !== ($task->opportunity ?? $task->workOrder->quote->opportunity)->registry_id))->toBeEmpty()
            ->and($tasks->filter(fn (Task $task): bool => $task->assignees->isEmpty()))->toBeEmpty();
    });

    it('closes some Tasks through the completion action, each with its own segnatempo', function (): void {
        seedActivityAnchors();

        app(QualificaSampleTaskSeeder::class)->run(tasks: 12);

        $completed = Task::query()
            ->whereHas('taskStatus', fn ($query) => $query->where('group', TaskStatusGroup::ClosedPositive->value))
            ->with('assignees')
            ->get();

        // Two outcomes in six are a completion (spec 0127: the action writes
        // the Task's segnatempo, owned by the completing assignee).
        expect($completed)->toHaveCount(4)
            ->and($completed->filter(fn (Task $task): bool => $task->completion_date === null))->toBeEmpty()
            ->and(TimeEntry::query()->count())->toBe(4)
            ->and($completed->filter(fn (Task $task): bool => ! TimeEntry::query()
                ->where('task_id', $task->id)
                ->where('user_id', $task->assignees->first()->id)
                ->exists()))->toBeEmpty();
    });

    it('skips itself when there is no active task type', function (): void {
        seedActivityAnchors();
        TaskType::query()->update(['is_active' => false]);

        test()->seed(QualificaSampleTaskSeeder::class);

        expect(Task::query()->count())->toBe(0);
    });

    it('never hangs a Task on an opportunity at or below the watermark', function (): void {
        seedActivityAnchors();
        $watermark = (int) Opportunity::query()->max('id');

        app(QualificaSampleTaskSeeder::class)->run(sinceOpportunityId: $watermark);

        expect(Task::query()->count())->toBe(0);
    });
});

describe('QualificaSampleTimeEntrySeeder', function (): void {
    it('logs entries whose links follow the D-5 rule', function (): void {
        seedActivityAnchors();
        app(QualificaSampleTaskSeeder::class)->run(tasks: 12);
        $completionEntries = TimeEntry::query()->count();

        app(QualificaSampleTimeEntrySeeder::class)->run(timeEntries: 15);

        $entries = TimeEntry::query()->with(['task.assignees', 'opportunity', 'workOrder.quote.opportunity'])->get();
        $onTask = $entries->whereNotNull('task_id');
        $standalone = $entries->whereNull('task_id');

        expect($entries)->toHaveCount($completionEntries + 15)
            // An entry on a Task takes the Task's title and is owned by one of its assignees.
            ->and($onTask->filter(fn (TimeEntry $entry): bool => $entry->title !== $entry->task->title
                || ! $entry->task->assignees->contains('id', $entry->user_id)))->toBeEmpty()
            // Without a Task: a commessa OR an opportunity, never both, and its client.
            ->and($standalone->filter(fn (TimeEntry $entry): bool => ($entry->opportunity_id === null) === ($entry->work_order_id === null)))->toBeEmpty()
            ->and($standalone->filter(fn (TimeEntry $entry): bool => $entry->registry_id !== ($entry->opportunity ?? $entry->workOrder->quote->opportunity)->registry_id))->toBeEmpty()
            ->and(WorkOrder::query()->count())->toBeGreaterThan(0);
    });

    it('skips itself when there is no opportunity in the batch', function (): void {
        seedActivityAnchors();

        app(QualificaSampleTimeEntrySeeder::class)->run(sinceOpportunityId: (int) Opportunity::query()->max('id'));

        expect(TimeEntry::query()->count())->toBe(0);
    });
});
