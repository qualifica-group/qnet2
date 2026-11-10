<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ExportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Work orders and contracts: ignored columns outside the category, export over HTTP (spec 0374): AC-007, AC-011.

uses(RefreshDatabase::class);

it('ignores a filter and a sort on an attribute outside the category and rejects unknown ones (AC-007)', function (string $grid, AttributeContext $context, Closure $makeRecord) {
    [$root] = categoryTabAttribute($context, 'text', ['code' => 'in_field']);
    categoryTabAttribute($context, 'text', ['code' => 'out_field']);
    $makeRecord($root);
    $makeRecord($root);
    Sanctum::actingAs(categoryTabGridActor($grid));
    $page = ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => categoryTabFilters($root)];

    $response = $this->postJson("/api/tables/{$grid}/rows", [
        ...$page,
        'filterModel' => ['attr.out_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'nothing']],
        'sortModel' => [['colId' => 'attr.out_field', 'sort' => 'asc']],
    ])->assertOk();

    expect($response->json('items'))->toHaveCount(2)
        ->and(array_keys($response->json('items.0')))->toContain('attr.in_field')->not->toContain('attr.out_field');
    $this->postJson("/api/tables/{$grid}/rows", [...$page, 'sortModel' => [['colId' => 'attr.unknown', 'sort' => 'asc']]])->assertUnprocessable();
})->with([
    'work-orders' => ['work-orders', AttributeContext::WorkOrder, fn ($root) => categoryTabMultiWorkOrder(categoryTabQuote([$root]))],
    'contracts' => ['contracts', AttributeContext::Quote, fn ($root) => categoryTabContract([$root])],
]);

it('exports the work orders attribute values and honours a filter, a sort and the allow-list over HTTP (AC-011)', function () {
    Storage::fake('local');
    [$root] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    foreach (['beta', 'alpha'] as $value) {
        categoryTabMultiWorkOrder(categoryTabQuote([$root]))->forceFill(['attribute_values' => ['wo_field' => $value]])->save();
    }
    $actor = categoryTabGridActor('work-orders');
    $actor->givePermissionTo(Permission::findOrCreate('work-orders.export', 'web'));
    Sanctum::actingAs($actor);
    $payload = fn (array $extra): array => ['format' => 'csv', 'columns' => [['colId' => 'attr.wo_field', 'header' => 'WO']], 'advancedFilters' => categoryTabFilters($root), ...$extra];
    $exported = function (array $extra) use ($payload): array {
        $response = $this->postJson('/api/exports/work-orders', $payload($extra))->assertCreated();
        $run = ExportRun::findOrFail($response->json('data.export_run.id'));

        return array_column(array_slice(categoryTabCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 1), 0);
    };

    expect($exported(['sortModel' => [['colId' => 'attr.wo_field', 'sort' => 'asc']]]))->toBe(['alpha', 'beta'])
        ->and($exported(['filterModel' => ['attr.wo_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'bet']]]))->toBe(['beta']);
    $this->postJson('/api/exports/work-orders', $payload(['sortModel' => [['colId' => 'attr.unknown', 'sort' => 'asc']]]))->assertUnprocessable();
});
