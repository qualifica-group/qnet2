<?php

use App\Enums\WorkOrderType;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0352, B4: `project-work-orders` shares the `work-orders` custom fields (same columns as Commesse).

uses(RefreshDatabase::class);

it('shows a work-orders custom field as a custom.* column with its value in project-work-orders', function () {
    CustomFieldDefinition::factory()->forEntity('work-orders')->ofType('text')->create(['key' => 'site_note', 'label' => 'Site note']);
    $project = typedWorkOrder(WorkOrderType::Project);
    CustomFieldValue::factory()->forEntity('work-orders', $project->id)->create(['values' => ['site_note' => 'hello']]);
    Sanctum::actingAs(projectWorkOrderViewer());

    $columns = collect($this->getJson('/api/tables/project-work-orders/columns')->assertOk()->json('data.columns'))->keyBy('id');
    $rows = $this->postJson('/api/tables/project-work-orders/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items');

    expect($columns->has('custom.site_note'))->toBeTrue()
        ->and($rows[0]['custom.site_note'] ?? $rows[0]['custom']['site_note'] ?? null)->toBe('hello');
});

it('does not list project-work-orders as a custom field entity', function () {
    Permission::findOrCreate('custom-fields.viewAny');
    $actor = projectWorkOrderViewer();
    $actor->givePermissionTo('custom-fields.viewAny');
    Sanctum::actingAs($actor);

    $entityTypes = collect($this->getJson('/api/custom-fields/entities')->assertOk()->json('data'))->pluck('entity_type');

    expect($entityTypes)->toContain('work-orders')->not->toContain('project-work-orders');
});
