<?php

use App\Models\BusinessFunction;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('workOrderWorkflowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("work-order-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-order-workflows.{$ability}");
        }

        return $user;
    }
}

function workOrderWorkflowPayload(array $overrides = []): array
{
    return [
        'name' => 'SOA',
        'is_active' => true,
        'criteria' => [['field' => 'business_function_id', 'value_id' => BusinessFunction::factory()->create()->id]],
        'statuses' => [
            ['name' => 'Documents', 'group' => 'in_progress', 'progress_percentage' => 20, 'color' => '#112233'],
            ['name' => 'Review', 'group' => 'in_progress', 'description' => 'Under review'],
        ],
        ...$overrides,
    ];
}

// AC-002
it('POST creates a workflow with the contract shape and the set order', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['create']));

    $response = $this->postJson('/api/work-order-workflows', workOrderWorkflowPayload())->assertCreated();

    $response->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => [
            'id', 'name', 'is_active', 'created_at', 'updated_at',
            'criteria' => [['id', 'field', 'value_id', 'value_label']],
            'statuses' => [['id', 'name', 'description', 'color', 'group', 'system_key', 'progress_percentage', 'sort_order']],
        ], 'permissions']);

    $statuses = collect($response->json('data.statuses'));

    expect($statuses->pluck('name')->all())->toBe(['Aperta', 'Documents', 'Review', 'Chiusa con esito positivo', 'Chiusa con esito negativo'])
        ->and($statuses->pluck('sort_order')->all())->toBe([0, 10, 20, 30, 40])
        ->and($statuses->pluck('system_key')->all())->toBe(['open', null, null, 'completed', 'closed'])
        ->and($statuses[1]['progress_percentage'])->toBe(20)
        ->and($response->json('data.criteria.0.value_label'))->not->toBeEmpty();
});

it('GET show returns the workflow detail', function () {
    $workflow = workOrderWorkflowFor('Show', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['view']));

    $this->getJson("/api/work-order-workflows/{$workflow->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $workflow->id)
        ->assertJsonCount(4, 'data.statuses');
});

it('GET show: 404 for an unknown workflow', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['view']));

    $this->getJson('/api/work-order-workflows/999999')->assertNotFound();
});

// AC-003 via HTTP
it('PATCH syncs the set: customs by id, new created, omitted deleted, system rows descriptive-only', function () {
    $workflow = workOrderWorkflowFor('Set', ['business_function_id' => BusinessFunction::factory()->create()->id], ['Keep', 'Drop']);
    [$open, $keep, $drop, $completed, $closed] = $workflow->statuses->all();
    Sanctum::actingAs(workOrderWorkflowUserWith(['update']));

    $response = $this->patchJson("/api/work-order-workflows/{$workflow->id}", [
        'statuses' => [
            ['id' => $closed->id, 'name' => 'Archived', 'group' => 'open', 'progress_percentage' => 100],
            ['id' => $keep->id, 'name' => 'Kept', 'group' => 'in_progress', 'progress_percentage' => 50],
            ['name' => 'Brand new', 'group' => 'in_progress'],
            ['id' => $open->id, 'name' => 'Start', 'group' => 'closed', 'color' => '#abcdef'],
        ],
    ])->assertOk();

    $statuses = collect($response->json('data.statuses'));

    expect($statuses->pluck('name')->all())->toBe(['Start', 'Kept', 'Brand new', 'Chiusa con esito positivo', 'Archived'])
        ->and($statuses->pluck('group')->all())->toBe(['open', 'in_progress', 'in_progress', 'completed', 'closed'])
        ->and($statuses->contains('id', $drop->id))->toBeFalse();
});

it('PUT updates name and is_active', function () {
    $workflow = workOrderWorkflowFor('Old', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['update']));

    $this->putJson("/api/work-order-workflows/{$workflow->id}", ['name' => 'New', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.is_active', false);
});

it('DELETE removes the workflow and returns 204', function () {
    $workflow = workOrderWorkflowFor('Gone', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['delete']));

    $this->deleteJson("/api/work-order-workflows/{$workflow->id}")->assertNoContent();

    $this->assertDatabaseMissing('work_order_workflows', ['id' => $workflow->id]);
    $this->assertDatabaseMissing('work_order_workflow_statuses', ['work_order_workflow_id' => $workflow->id]);
});

// AC-004
it('rejects every invalid payload with 422 and writes nothing', function (array $overrides) {
    Sanctum::actingAs(workOrderWorkflowUserWith(['create']));

    $this->postJson('/api/work-order-workflows', workOrderWorkflowPayload($overrides))->assertUnprocessable();

    expect(WorkOrderWorkflow::count())->toBe(0);
})->with([
    'field not allowed' => [['criteria' => [['field' => 'source_id', 'value_id' => 1]]]],
    'value_id missing in table' => [['criteria' => [['field' => 'business_function_id', 'value_id' => 999999]]]],
    'duplicate field' => [['criteria' => [
        ['field' => 'business_function_id', 'value_id' => 1],
        ['field' => 'business_function_id', 'value_id' => 2],
    ]]],
    'no criteria' => [['criteria' => []]],
    'progress above 100' => [['statuses' => [['name' => 'X', 'group' => 'in_progress', 'progress_percentage' => 101]]]],
    'progress below 0' => [['statuses' => [['name' => 'X', 'group' => 'in_progress', 'progress_percentage' => -1]]]],
    'group not in WorkOrderStatus' => [['statuses' => [['name' => 'X', 'group' => 'validated']]]],
    'bad color' => [['statuses' => [['name' => 'X', 'group' => 'open', 'color' => 'blue']]]],
    'unknown system_key' => [['statuses' => [['name' => 'X', 'group' => 'open', 'system_key' => 'closed_won']]]],
    'name missing' => [['name' => '']],
]);

it('POST 422 when the criteria combination already exists, no second workflow written', function () {
    $function = BusinessFunction::factory()->create();
    workOrderWorkflowFor('First', ['business_function_id' => $function->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['create']));

    $this->postJson('/api/work-order-workflows', workOrderWorkflowPayload([
        'name' => 'Second',
        'criteria' => [['field' => 'business_function_id', 'value_id' => $function->id]],
    ]))->assertUnprocessable();

    expect(WorkOrderWorkflow::count())->toBe(1);
});

it('PATCH 422 for a status id outside the workflow, nothing changed', function () {
    $workflow = workOrderWorkflowFor('Set', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    $other = workOrderWorkflowFor('Other', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['update']));

    $this->patchJson("/api/work-order-workflows/{$workflow->id}", [
        'statuses' => [['id' => $other->statuses->get(1)->id, 'name' => 'Stolen', 'group' => 'in_progress']],
    ])->assertUnprocessable();

    $this->assertDatabaseMissing('work_order_workflow_statuses', ['name' => 'Stolen']);
});

// AC-005
it('answers 403 on every endpoint without permission and writes nothing', function () {
    $workflow = workOrderWorkflowFor('Locked', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith([]));

    $this->getJson("/api/work-order-workflows/{$workflow->id}")->assertForbidden();
    $this->postJson('/api/work-order-workflows', workOrderWorkflowPayload())->assertForbidden();
    $this->patchJson("/api/work-order-workflows/{$workflow->id}", ['name' => 'Nope'])->assertForbidden();
    $this->deleteJson("/api/work-order-workflows/{$workflow->id}")->assertForbidden();
    $this->getJson('/api/work-order-workflows/criterion-fields')->assertForbidden();
    $this->getJson('/api/work-order-workflows/default-statuses')->assertForbidden();
    $this->putJson('/api/work-order-workflows/default-statuses', ['statuses' => [['name' => 'X', 'group' => 'open']]])->assertForbidden();

    $this->assertDatabaseHas('work_order_workflows', ['id' => $workflow->id, 'name' => 'Locked']);
    expect(WorkOrderWorkflow::count())->toBe(1);
});

it('PUT default-statuses needs update, not just view', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['view']));

    $this->putJson('/api/work-order-workflows/default-statuses', ['statuses' => [['name' => 'X', 'group' => 'open']]])
        ->assertForbidden();
});

it('shows the navigation node only with work-order-workflows.viewAny', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith([]));
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('work-order-workflows');

    Sanctum::actingAs(workOrderWorkflowUserWith(['viewAny']));
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->toContain('work-order-workflows');
});

it('place the navigation node right after quote-workflows', function () {
    $keys = collect(config('navigation.configuration.commercial.children', []))->pluck('key');

    expect($keys->search('work-order-workflows'))->toBe($keys->search('quote-workflows') + 1);
});

// default-statuses + criterion-fields
it('GET criterion-fields lists the three allow-listed fields', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['view']));

    $data = collect($this->getJson('/api/work-order-workflows/criterion-fields')->assertOk()->json('data'))->keyBy('field');

    expect($data->keys()->all())->toBe(['business_function_id', 'product_category_id', 'product_category_branch_id'])
        ->and($data['business_function_id']['for_select_resource'])->toBe('business-functions')
        ->and($data['product_category_branch_id']['for_select_resource'])->toBe('product-category-branches')
        ->and($data['business_function_id'])->toMatchArray(['source' => 'native', 'multi_valued' => true, 'inherited' => false]);
});

it('GET default-statuses exposes the global set in order', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['view']));

    $data = collect($this->getJson('/api/work-order-workflows/default-statuses')->assertOk()->json('data'));

    expect($data->pluck('system_key')->all())->toBe(['open', 'completed', 'closed'])
        ->and($data->first())->toHaveKeys(['id', 'name', 'description', 'color', 'group', 'system_key', 'progress_percentage', 'sort_order']);
});

it('PUT default-statuses syncs the customs and keeps the system rows', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['view', 'update']));
    $open = globalWorkOrderStatus('open');

    $data = collect($this->putJson('/api/work-order-workflows/default-statuses', [
        'statuses' => [
            ['id' => $open->id, 'name' => 'Start', 'group' => 'open', 'progress_percentage' => 5],
            ['name' => 'In corso', 'group' => 'in_progress', 'color' => '#00ff00', 'progress_percentage' => 50],
        ],
    ])->assertOk()->json('data'));

    expect($data->pluck('name')->all())->toBe(['Start', 'In corso', 'Chiusa con esito positivo', 'Chiusa con esito negativo'])
        ->and($data->pluck('system_key')->all())->toBe(['open', null, 'completed', 'closed'])
        ->and($data->first()['progress_percentage'])->toBe(5);
});

it('PUT default-statuses 422 without statuses or with an out-of-range percentage', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith(['update']));

    $this->putJson('/api/work-order-workflows/default-statuses', [])->assertUnprocessable()->assertJsonValidationErrors('statuses');
    $this->putJson('/api/work-order-workflows/default-statuses', [
        'statuses' => [['name' => 'X', 'group' => 'open', 'progress_percentage' => 101]],
    ])->assertUnprocessable();
});

it('records create, update and delete in the activity log', function () {
    $actor = workOrderWorkflowUserWith(['create', 'update', 'delete']);
    Sanctum::actingAs($actor);

    $id = $this->postJson('/api/work-order-workflows', workOrderWorkflowPayload())->assertCreated()->json('data.id');
    $this->patchJson("/api/work-order-workflows/{$id}", ['name' => 'Renamed'])->assertOk();
    $this->deleteJson("/api/work-order-workflows/{$id}")->assertNoContent();

    foreach (['created', 'updated', 'deleted'] as $event) {
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'work_order_workflow',
            'subject_id' => $id,
            'event' => $event,
            'causer_id' => $actor->id,
        ]);
    }
});

// AC-009 via HTTP
it('DELETE reassigns the work orders of the deleted workflow', function () {
    $workflow = workOrderWorkflowFor('Doomed', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses->first()->id])->save();
    Sanctum::actingAs(workOrderWorkflowUserWith(['delete']));

    $this->deleteJson("/api/work-order-workflows/{$workflow->id}")->assertNoContent();

    $workOrder->refresh();
    expect($workOrder->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('open')->id)
        ->and(WorkOrderWorkflowStatus::query()->where('work_order_workflow_id', $workflow->id)->exists())->toBeFalse();
});
