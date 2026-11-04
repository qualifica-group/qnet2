<?php

use App\Enums\WorkOrderType;
use App\Models\ExportRun;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0352, B4: category strip, export scope and menu entry (AC-015, AC-016).

uses(RefreshDatabase::class);

it('AC-015: the Commesse category strip does not count project work orders', function () {
    $category = ProductCategory::factory()->create();
    WorkOrder::factory()->create(['quote_id' => categoryTabQuote([$category])->id, 'type' => WorkOrderType::Processing]);
    WorkOrder::factory()->create(['quote_id' => categoryTabQuote([$category])->id, 'type' => WorkOrderType::Project]);
    Sanctum::actingAs(categoryTabGridActor('work-orders'));

    $counts = collect($this->getJson('/api/work-orders/product-categories')->assertOk()->json('data.categories'))->pluck('records_count', 'id')->all();

    expect($counts)->toBe([$category->id => 1]);
});

it('AC-015: exporting work-orders with work_order_type=processing leaves project out and freezes the scope', function () {
    Storage::fake('local');
    $processing = typedWorkOrder(WorkOrderType::Processing);
    typedWorkOrder(WorkOrderType::Project);
    Sanctum::actingAs(projectWorkOrderViewer());
    $payload = ['format' => 'csv', 'columns' => [['colId' => 'code', 'header' => 'Code']]];

    $response = $this->postJson('/api/exports/work-orders', [...$payload, 'work_order_type' => 'processing'])->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->state['work_order_type'])->toBe('processing')
        ->and($run->fresh()->row_count)->toBe(1)
        ->and(Storage::disk('local')->get($run->fresh()->file_path))->toContain($processing->code);

    $all = $this->postJson('/api/exports/work-orders', $payload)->assertCreated();

    expect(ExportRun::findOrFail($all->json('data.export_run.id'))->row_count)->toBe(2);
});

it('AC-015: the project-work-orders domain is exportable with project rows only; an invalid scope is a 422', function () {
    Storage::fake('local');
    typedWorkOrder(WorkOrderType::Processing);
    typedWorkOrder(WorkOrderType::Project);
    Sanctum::actingAs(projectWorkOrderViewer());
    $payload = ['format' => 'csv', 'columns' => [['colId' => 'code', 'header' => 'Code']]];

    $response = $this->postJson('/api/exports/project-work-orders', $payload)->assertCreated();

    expect(ExportRun::findOrFail($response->json('data.export_run.id'))->row_count)->toBe(1);

    $this->postJson('/api/exports/work-orders', [...$payload, 'work_order_type' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('work_order_type');
});

it('AC-016: the menu has Commesse di Progetto right after Commesse for work-orders.view', function () {
    Permission::findOrCreate('work-orders.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('work-orders.view');
    Sanctum::actingAs($user);

    $items = collect($this->getJson('/api/navigation')->assertOk()->json('data'))
        ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])->values();
    $keys = $items->pluck('key')->all();
    $entry = $items->firstWhere('key', 'project-work-orders');

    expect($entry['label'])->toBe('navigation.projectWorkOrders')
        ->and($entry['route'])->toBe('/project-work-orders')
        ->and($entry['icon'])->toBe('clipboard-list')
        ->and(array_search('project-work-orders', $keys, true))->toBe(array_search('work-orders', $keys, true) + 1);
});

it('AC-016: the menu entry is absent without work-orders.view', function () {
    Sanctum::actingAs(User::factory()->create());

    $keys = collect($this->getJson('/api/navigation')->assertOk()->json('data'))
        ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])->pluck('key')->all();

    expect($keys)->not->toContain('project-work-orders')->not->toContain('work-orders');
});
