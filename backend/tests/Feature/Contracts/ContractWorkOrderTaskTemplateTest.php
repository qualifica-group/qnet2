<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\QuoteLine;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * POST /api/contracts/{contract}/work-orders (spec 0124, D-9): the same
 * task-generation outcome as POST /api/work-orders (WorkOrderTaskTemplate
 * GenerationTest), reached through the Contract "Programma" dialog instead
 * (AC-018).
 */
uses(RefreshDatabase::class);

if (! function_exists('contractTaskTemplateActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractTaskTemplateActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'program'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }
        foreach (['viewAny', 'view', 'create'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

it('AC-018: generates the template\'s tasks exactly like POST /api/work-orders', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $supervisorA = User::factory()->create();
    $supervisorB = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create([
        'title' => 'Riga programma', 'estimated_minutes' => 15, 'due_offset_days' => 2,
    ]);
    $actor = contractTaskTemplateActor(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Programmata con modello', 'type' => 'processing',
        'start_date' => '2026-11-02', 'supervisor_ids' => [$supervisorA->id, $supervisorB->id],
        'quote_line_ids' => [$line->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    expect(WorkOrder::find($response->json('data.id'))->task_template_id)->toBe($template->id)
        ->and($response->json('data.task_template'))->toBe(['id' => $template->id, 'name' => $template->name]);

    $task = Task::query()->where('work_order_id', $response->json('data.id'))->sole();

    expect($task->title)->toBe('Riga programma')
        ->and($task->estimated_minutes)->toBe(15)
        ->and($task->start_date->toDateString())->toBe('2026-11-02')
        ->and($task->end_date->toDateString())->toBe('2026-11-04')
        ->and($task->creator_id)->toBe($actor->id)
        ->and($task->requester_id)->toBe($actor->id);

    expect($task->assignees()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$supervisorA->id, $supervisorB->id])->sort()->values()->all());
});

it('AC-018: an inactive task_template_id on the contract dialog is 422 and creates nothing', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $supervisor = User::factory()->create();
    $inactiveTemplate = TaskTemplate::factory()->inactive()->create();
    $actor = contractTaskTemplateActor(['contracts.program']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Modello inattivo', 'type' => 'processing',
        'start_date' => '2026-11-02', 'supervisor_ids' => [$supervisor->id],
        'quote_line_ids' => [$line->id],
        'task_template_id' => $inactiveTemplate->id,
    ])->assertStatus(422)->assertJsonValidationErrors('task_template_id');

    expect(WorkOrder::count())->toBe(0);
});
