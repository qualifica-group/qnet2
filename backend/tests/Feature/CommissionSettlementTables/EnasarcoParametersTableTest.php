<?php

use App\Models\CommissionSettlement;
use App\Models\EnasarcoParameter;
use App\Models\ExportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const ENASARCO_DOMAIN = 'enasarco-parameters';

/**
 * @param  array<string, mixed>  $attributes
 */
function tableEnasarcoParameter(int $year, array $attributes = []): EnasarcoParameter
{
    return EnasarcoParameter::factory()->create(['year' => $year, ...$attributes]);
}

it('AC-018: exposes the contract columns, the number filters and the year desc default sort', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny']));

    $data = $this->getJson('/api/tables/enasarco-parameters/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe(['id', 'year', 'total_rate', 'agent_share_percent', 'single_firm_ceiling', 'multi_firm_ceiling', 'updated_at'])
        ->and($data['defaultSort'])->toBe([['columnId' => 'year', 'direction' => 'desc']])
        ->and($data)->not->toHaveKey('row_grouping')
        ->and($columns['year'])->toMatchArray(['type' => 'number', 'sortable' => true, 'filterType' => 'number', 'label' => 'enasarcoParameters.columns.year'])
        ->and($columns['multi_firm_ceiling'])->toMatchArray(['type' => 'number', 'sortable' => true, 'filterType' => 'number'])
        ->and($columns['updated_at'])->toMatchArray(['type' => 'date', 'sortable' => true, 'filterable' => false])
        ->and(array_column($data['actions'], 'key'))->toBe([]);
});

it('AC-018: the actions follow the permissions of the actor', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny', 'view', 'delete', 'viewActivity']));

    $actions = $this->getJson('/api/tables/enasarco-parameters/columns')->assertOk()->json('data.actions');

    expect(array_column($actions, 'key'))->toBe(['view', 'delete', 'activity']);
});

it('AC-018: rows come year desc with string values and the update and delete abilities', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny', 'update']));
    $y2025 = tableEnasarcoParameter(2025, ['total_rate' => '17.00', 'agent_share_percent' => '50.00', 'single_firm_ceiling' => '45000.00', 'multi_firm_ceiling' => '26000.00']);
    $y2026 = tableEnasarcoParameter(2026);

    $rows = settlementTableRows(ENASARCO_DOMAIN)['items'];

    expect(array_column($rows, 'id'))->toBe([$y2026->id, $y2025->id])
        ->and($rows[1])->toMatchArray([
            'year' => 2025, 'total_rate' => '17.00', 'agent_share_percent' => '50.00',
            'single_firm_ceiling' => '45000.00', 'multi_firm_ceiling' => '26000.00',
            'abilities' => ['update' => true, 'delete' => false],
        ])
        ->and($rows[1]['updated_at'])->not->toBeNull();
});

it('AC-018: sorts and filters on every number column', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny']));
    $a = tableEnasarcoParameter(2024, ['total_rate' => '16.50', 'agent_share_percent' => '50.00', 'single_firm_ceiling' => '40000.00', 'multi_firm_ceiling' => '24000.00']);
    $b = tableEnasarcoParameter(2025, ['total_rate' => '17.00', 'agent_share_percent' => '48.00', 'single_firm_ceiling' => '45000.00', 'multi_firm_ceiling' => '26000.00']);
    $number = static fn (string $type, float $value): array => ['filterType' => 'number', 'type' => $type, 'filter' => $value];
    $ids = static fn (array $filter): array => settlementTableIds(ENASARCO_DOMAIN, ['filterModel' => $filter, 'sortModel' => [['colId' => 'id', 'sort' => 'asc']]]);

    expect(settlementTableIds(ENASARCO_DOMAIN, ['sortModel' => [['colId' => 'year', 'sort' => 'asc']]]))->toBe([$a->id, $b->id])
        ->and(settlementTableIds(ENASARCO_DOMAIN, ['sortModel' => [['colId' => 'single_firm_ceiling', 'sort' => 'desc']]]))->toBe([$b->id, $a->id])
        ->and($ids(['year' => $number('equals', 2025)]))->toBe([$b->id])
        ->and($ids(['total_rate' => $number('lessThan', 17)]))->toBe([$a->id])
        ->and($ids(['agent_share_percent' => $number('lessThan', 49)]))->toBe([$b->id])
        ->and($ids(['single_firm_ceiling' => $number('greaterThan', 41000)]))->toBe([$b->id])
        ->and($ids(['multi_firm_ceiling' => $number('equals', 24000)]))->toBe([$a->id]);
});

it('AC-018: a user without enasarco-parameters.viewAny gets 403 on columns, rows and values', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['view', 'create']));

    $this->getJson('/api/tables/enasarco-parameters/columns')->assertForbidden();
    $this->postJson('/api/tables/enasarco-parameters/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->postJson('/api/tables/enasarco-parameters/values', ['columnId' => 'year', 'filterModel' => []])->assertForbidden();
});

it('AC-018: exports the parameters with enasarco-parameters.export and refuses without it', function () {
    Storage::fake((string) config('exports.disk'));
    tableEnasarcoParameter(2026);
    $body = ['format' => 'csv', 'columns' => [['colId' => 'year', 'header' => 'Year'], ['colId' => 'total_rate', 'header' => 'Rate']]];

    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny', 'export']));
    $this->postJson('/api/exports/enasarco-parameters', $body)->assertSuccessful();

    expect(ExportRun::query()->latest('id')->firstOrFail()->row_count)->toBe(1);

    Queue::fake();
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny']));
    $this->postJson('/api/exports/enasarco-parameters', $body)->assertForbidden();
});

it('AC-018: bulk delete removes an unused year and refuses a year used by an issued settlement', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny', 'delete']));
    $free = tableEnasarcoParameter(2024);
    $used = tableEnasarcoParameter(2026);
    CommissionSettlement::factory()->issued()->create(['enasarco_enabled' => true, 'period_from' => '2026-03-01', 'period_to' => '2026-03-31']);

    $this->postJson('/api/tables/enasarco-parameters/bulk-delete', ['ids' => [$free->id, $used->id]])->assertOk()
        ->assertJsonPath('data.deleted', 1)->assertJsonPath('data.failed.0.id', $used->id)->assertJsonPath('data.failed.0.reason', 'guarded');

    expect(EnasarcoParameter::query()->pluck('year')->all())->toBe([2026]);

    Sanctum::actingAs(enasarcoParameterUserWith(['viewAny']));
    $this->postJson('/api/tables/enasarco-parameters/bulk-delete', ['ids' => [$used->id]])->assertOk()
        ->assertJsonPath('data.deleted', 0)->assertJsonPath('data.failed.0.reason', 'forbidden');
});
