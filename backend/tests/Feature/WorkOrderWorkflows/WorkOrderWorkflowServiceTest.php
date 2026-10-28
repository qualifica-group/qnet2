<?php

use App\DataObjects\WorkOrderWorkflows\CreateWorkOrderWorkflowData;
use App\DataObjects\WorkOrderWorkflows\UpdateWorkOrderWorkflowData;
use App\Models\BusinessFunction;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function statusSnapshot(WorkOrderWorkflow $workflow): array
{
    return $workflow->statuses->map(fn ($status) => [$status->name, $status->system_key, $status->sort_order, $status->group->value])->all();
}

// AC-002
it('creates a workflow with open, the custom rows in order, then completed and closed at step 10', function () {
    $function = BusinessFunction::factory()->create();

    $workflow = app(WorkOrderWorkflowService::class)->create(CreateWorkOrderWorkflowData::fromValidated([
        'name' => 'SOA',
        'criteria' => [['field' => 'business_function_id', 'value_id' => $function->id]],
        'statuses' => [
            ['name' => 'Documents', 'group' => 'in_progress', 'progress_percentage' => 20, 'color' => '#112233'],
            ['name' => 'Review', 'group' => 'in_progress', 'description' => 'Under review'],
        ],
    ]));

    expect(statusSnapshot($workflow))->toBe([
        ['Aperta', 'open', 0, 'open'],
        ['Documents', null, 10, 'in_progress'],
        ['Review', null, 20, 'in_progress'],
        ['Completata', 'completed', 30, 'completed'],
        ['Chiusa', 'closed', 40, 'closed'],
    ])
        ->and($workflow->is_active)->toBeTrue()
        ->and($workflow->criteria)->toHaveCount(1)
        ->and($workflow->statuses[1]->progress_percentage)->toBe(20)
        ->and($workflow->statuses[1]->color)->toBe('#112233');
});

// AC-003
it('updates a set: customs by id, new created, omitted deleted, system rows descriptive-only', function () {
    $workflow = workOrderWorkflowFor('Set', ['business_function_id' => BusinessFunction::factory()->create()->id], ['Keep', 'Drop']);
    [$open, $keep, $drop, $completed, $closed] = $workflow->statuses->all();

    $updated = app(WorkOrderWorkflowService::class)->update($workflow, UpdateWorkOrderWorkflowData::fromValidated([
        'statuses' => [
            ['id' => $closed->id, 'name' => 'Archived', 'group' => 'open', 'progress_percentage' => 100],
            ['id' => $keep->id, 'name' => 'Kept', 'group' => 'in_progress', 'progress_percentage' => 50],
            ['name' => 'Brand new', 'group' => 'in_progress'],
            ['id' => $open->id, 'name' => 'Start', 'group' => 'closed', 'color' => '#abcdef'],
        ],
    ]));

    expect(statusSnapshot($updated))->toBe([
        ['Start', 'open', 0, 'open'],
        ['Kept', null, 10, 'in_progress'],
        ['Brand new', null, 20, 'in_progress'],
        ['Completata', 'completed', 30, 'completed'],
        ['Archived', 'closed', 40, 'closed'],
    ])
        ->and($updated->statuses->firstWhere('id', $keep->id)->progress_percentage)->toBe(50)
        ->and($updated->statuses->firstWhere('id', $closed->id)->progress_percentage)->toBe(100)
        ->and($updated->statuses->firstWhere('id', $open->id)->color)->toBe('#abcdef')
        ->and($updated->statuses->contains('id', $drop->id))->toBeFalse();
});

it('rejects an unknown custom status id', function () {
    $workflow = workOrderWorkflowFor('Set', ['business_function_id' => BusinessFunction::factory()->create()->id]);

    expect(fn () => app(WorkOrderWorkflowService::class)->update($workflow, UpdateWorkOrderWorkflowData::fromValidated([
        'statuses' => [['id' => 999999, 'name' => 'Ghost', 'group' => 'in_progress']],
    ])))->toThrow(HttpException::class);
});

// AC-004 (service level): same criteria combination, regardless of order.
it('refuses a second workflow with the same criteria combination and writes nothing', function () {
    $function = BusinessFunction::factory()->create();
    workOrderWorkflowFor('First', ['business_function_id' => $function->id]);

    expect(fn () => workOrderWorkflowFor('Second', ['business_function_id' => $function->id]))
        ->toThrow(HttpException::class)
        ->and(WorkOrderWorkflow::query()->count())->toBe(1);
});

it('allows a different combination and lets a workflow keep its own on update', function () {
    $function = BusinessFunction::factory()->create();
    $first = workOrderWorkflowFor('First', ['business_function_id' => $function->id]);
    workOrderWorkflowFor('Other', ['business_function_id' => BusinessFunction::factory()->create()->id]);

    $updated = app(WorkOrderWorkflowService::class)->update($first, UpdateWorkOrderWorkflowData::fromValidated([
        'name' => 'Renamed',
        'criteria' => [['field' => 'business_function_id', 'value_id' => $function->id]],
    ]));

    expect($updated->name)->toBe('Renamed');
});

it('syncs the global default set the same way', function () {
    $service = app(WorkOrderWorkflowService::class);
    $default = $service->defaultStatuses();

    $synced = $service->syncDefaultStatuses([
        ['id' => $default[0]->id, 'name' => 'Opened', 'description' => null, 'color' => null, 'group' => 'open', 'progress_percentage' => 0],
        ['id' => null, 'name' => 'Global custom', 'description' => null, 'color' => null, 'group' => 'in_progress', 'progress_percentage' => 10],
    ]);

    expect($synced->pluck('name')->all())->toBe(['Opened', 'Global custom', 'Completata', 'Chiusa'])
        ->and($synced->pluck('sort_order')->all())->toBe([0, 10, 20, 30]);
});
