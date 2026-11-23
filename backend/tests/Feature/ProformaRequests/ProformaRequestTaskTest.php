<?php

declare(strict_types=1);

use App\Models\CompanySite;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskAssigned;
use App\Services\ProformaRequests\ProformaRequestTaskDispatcher;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CustomFieldDefinition::factory()->forEntity('company-sites')->ofType('relation')->create([
        'key' => ProformaRequestTaskDispatcher::RESPONSIBLE_FIELD,
        'relation_target' => ['entity_type' => 'users', 'cardinality' => 'one', 'for_select_resource' => 'users'],
    ]);
    $this->category = TaskCategory::factory()->create(['name' => 'Contabile', 'is_active' => true]);
    $this->defaultType = TaskType::factory()->create(['name' => 'Attività', 'is_default' => true, 'is_active' => true]);
});

/**
 * A company site whose proforma manager is the given user (null = field left empty).
 */
function proformaSiteManagedBy(?User $manager): CompanySite
{
    $site = CompanySite::factory()->create();

    if ($manager !== null) {
        CustomFieldValue::create([
            'entity_type' => 'company-sites',
            'entity_id' => $site->id,
            'values' => [ProformaRequestTaskDispatcher::RESPONSIBLE_FIELD => $manager->id],
        ]);
    }

    return $site;
}

function assignProformaSite(Quote $quote, CompanySite $site): void
{
    Quote::query()->whereKey($quote->id)->update(['company_site_id' => $site->id]);
}

function postProformaRequest(WorkOrder $workOrder, string $note = 'Please bill'): TestResponse
{
    return test()->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => $note]);
}

it('AC-001: opens one Task for the site proforma manager with requester, creator, work order, category, type and due date', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $actor = proformaUserWith(['create', 'view']);
    Sanctum::actingAs($actor);
    $workOrder = proformaWorkOrder([['consultancy']]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));

    postProformaRequest($workOrder)->assertCreated();

    $task = Task::query()->with('assignees')->sole();
    expect($task->assignees->pluck('id')->all())->toBe([$manager->id])
        ->and($task->requester_id)->toBe($actor->id)
        ->and($task->creator_id)->toBe($actor->id)
        ->and($task->work_order_id)->toBe($workOrder->id)
        ->and($task->task_category_id)->toBe($this->category->id)
        ->and($task->task_type_id)->toBe($this->defaultType->id)
        ->and($task->end_date->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and($task->title)->toBe(__('proforma_requests.task.title', ['code' => $workOrder->code]))
        ->and($task->description)->toContain('Please bill');
});

it('AC-002: the proforma manager receives one Task assignment notification', function () {
    $manager = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));
    Notification::fake();

    postProformaRequest($workOrder)->assertCreated();

    Notification::assertSentToTimes($manager, TaskAssigned::class, 1);
});

it('AC-003: several requests with the same manager open a single Task listing all of them', function () {
    $manager = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy'], ['institution', Registry::factory()->create()]]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));
    Notification::fake();

    postProformaRequest($workOrder)->assertCreated()->assertJsonCount(2, 'data');

    $task = Task::query()->sole();
    expect($task->description)
        ->toContain(__('proforma_requests.task.kinds.consultancy'))
        ->toContain(__('proforma_requests.task.kinds.institution'));
    Notification::assertSentToTimes($manager, TaskAssigned::class, 1);
});

it('AC-004: offers of sites with different managers open one Task per manager', function () {
    [$managerA, $managerB] = User::factory()->count(2)->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    [$workOrder, $quoteA, $quoteB] = multiQuoteWorkOrder();
    assignProformaSite($quoteA, proformaSiteManagedBy($managerA));
    assignProformaSite($quoteB, proformaSiteManagedBy($managerB));

    postProformaRequest($workOrder)->assertCreated();

    $tasks = Task::query()->with('assignees')->get();
    expect($tasks)->toHaveCount(2);
    $byManager = $tasks->keyBy(fn (Task $task) => $task->assignees->sole()->id);
    expect($byManager[$managerA->id]->description)->toContain($quoteA->code)->not->toContain($quoteB->code)
        ->and($byManager[$managerB->id]->description)->toContain($quoteB->code)->not->toContain($quoteA->code);
});

it('AC-005: a site with no manager, an offer with no site or an inactive manager open nothing, and the request still succeeds', function (string $case) {
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    Notification::fake();

    match ($case) {
        'empty field' => assignProformaSite($workOrder->quote, proformaSiteManagedBy(null)),
        'inactive manager' => assignProformaSite($workOrder->quote, proformaSiteManagedBy(User::factory()->create(['is_active' => false]))),
        'no site' => null,
    };

    postProformaRequest($workOrder)->assertCreated()->assertJsonCount(1, 'data');

    expect(Task::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['empty field', 'no site', 'inactive manager']);

it('AC-005: with one managed site and one empty site only the managed one opens a Task', function () {
    $manager = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    [$workOrder, $quoteA, $quoteB] = multiQuoteWorkOrder();
    assignProformaSite($quoteA, proformaSiteManagedBy($manager));
    assignProformaSite($quoteB, proformaSiteManagedBy(null));

    postProformaRequest($workOrder)->assertCreated()->assertJsonCount(2, 'data');

    expect(Task::query()->with('assignees')->sole()->assignees->pluck('id')->all())->toBe([$manager->id]);
});

it('AC-006: a renamed accounting category leaves the Task without a category', function () {
    $this->category->update(['name' => 'Accounting']);
    $manager = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));

    postProformaRequest($workOrder)->assertCreated();

    expect(Task::query()->sole()->task_category_id)->toBeNull();
});

it('AC-007: a refused request (409 duplicate, 422 nothing billable) opens no Task', function () {
    $manager = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));
    postProformaRequest($workOrder)->assertCreated();

    postProformaRequest($workOrder)->assertStatus(409);

    $nothingBillable = proformaWorkOrder([[null]]);
    assignProformaSite($nothingBillable->quote, proformaSiteManagedBy($manager));
    postProformaRequest($nothingBillable)->assertStatus(422);

    expect(Task::query()->count())->toBe(1);
});

it('AC-007: an unexpected failure while opening the Task rolls the requests back', function () {
    $manager = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));
    $this->mock(TaskService::class)->shouldReceive('create')->andThrow(new RuntimeException('task store down'));

    postProformaRequest($workOrder)->assertServerError();

    expect(ProformaRequest::query()->count())->toBe(0)
        ->and(Task::query()->count())->toBe(0);
});

it('AC-008: the manager sees the Task as its assignee without seeing the work order', function () {
    $manager = taskActorWith(['viewAny', 'view'], withViewAll: false);
    Sanctum::actingAs(proformaUserWith(['create', 'view']));
    $workOrder = proformaWorkOrder([['consultancy']]);
    assignProformaSite($workOrder->quote, proformaSiteManagedBy($manager));
    postProformaRequest($workOrder)->assertCreated();
    $task = Task::query()->sole();

    Sanctum::actingAs($manager);

    $this->getJson("/api/tasks/{$task->id}")->assertOk();
});
