<?php

use App\Models\BusinessFunction;
use App\Models\User;
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

it('rows: 403 without viewAny', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith([]));

    $this->postJson('/api/tables/work-order-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('rows: 200 with the expected row shape', function () {
    $function = BusinessFunction::factory()->create(['name' => 'Certification']);
    $workflow = workOrderWorkflowFor('Table Row Target', ['business_function_id' => $function->id], ['Custom']);
    Sanctum::actingAs(workOrderWorkflowUserWith(['viewAny', 'view', 'update', 'delete']));

    $row = collect($this->postJson('/api/tables/work-order-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('id', $workflow->id);

    expect($row['name'])->toBe('Table Row Target')
        ->and($row['criteria_fields'])->toBe(['workOrderWorkflows.criterionFields.business_function_id'])
        ->and($row['criteria_values'])->toBe(['Certification'])
        ->and($row['statuses_count'])->toBe(4)
        ->and($row['is_active'])->toBeTrue()
        ->and($row)->toHaveKey('updated_at')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'delete']);
});

it('rows: sort and filter on allow-listed columns work, unknown sort column is not honoured', function () {
    workOrderWorkflowFor('Bravo', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    workOrderWorkflowFor('Alpha', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['viewAny']));

    $names = fn (array $body) => collect($this->postJson('/api/tables/work-order-workflows/rows', ['startRow' => 0, 'endRow' => 25, ...$body])->assertOk()->json('items'))->pluck('name')->all();

    expect($names(['sortModel' => [['colId' => 'name', 'sort' => 'asc']]]))->toBe(['Alpha', 'Bravo']);
});

it('columns: declared ids, 403 without viewAny', function () {
    Sanctum::actingAs(workOrderWorkflowUserWith([]));
    $this->getJson('/api/tables/work-order-workflows/columns')->assertForbidden();

    Sanctum::actingAs(workOrderWorkflowUserWith(['viewAny']));
    $data = $this->getJson('/api/tables/work-order-workflows/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('work-order-workflows')
        ->and(collect($data['columns'])->pluck('id')->all())
        ->toBe(['id', 'name', 'criteria_fields', 'criteria_values', 'statuses_count', 'is_active', 'updated_at']);
});

it('row actions respect the actor permissions', function () {
    workOrderWorkflowFor('Plain', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderWorkflowUserWith(['viewAny', 'view']));

    $row = collect($this->postJson('/api/tables/work-order-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))->first();

    expect($row['actions'])->toContain('view')->not->toContain('delete');
});
