<?php

use App\Models\CommissionSettlementRun;
use App\Models\Company;
use App\Models\ExportRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const RUNS_DOMAIN = 'commission-settlement-runs';

/**
 * @param  array<string, mixed>  $attributes
 */
function tableRun(array $attributes = []): CommissionSettlementRun
{
    return CommissionSettlementRun::factory()->create($attributes);
}

/**
 * @return array<int, array{referent_id: int, company_id: int, reason: string}>
 */
function skippedPairs(int $count): array
{
    return array_fill(0, $count, ['referent_id' => 1, 'company_id' => 1, 'reason' => 'below_threshold']);
}

beforeEach(function () {
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny']));
});

it('AC-018: exposes the contract columns and filters and no row grouping', function () {
    $data = $this->getJson('/api/tables/commission-settlement-runs/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe(['id', 'created_at', 'period_from', 'period_to', 'company', 'settlements_count', 'skipped_count', 'created_by'])
        ->and($data)->not->toHaveKey('row_grouping')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc'], ['columnId' => 'id', 'direction' => 'desc']])
        ->and($data['actions'])->toBe([])
        ->and($columns['created_at'])->toMatchArray(['type' => 'date', 'sortable' => true, 'filterable' => false, 'label' => 'commissionSettlementRuns.columns.created_at'])
        ->and($columns['period_from'])->toMatchArray(['type' => 'date', 'sortable' => true, 'filterType' => 'date'])
        ->and($columns['period_to'])->toMatchArray(['type' => 'date', 'sortable' => true, 'filterType' => 'date'])
        ->and($columns['company'])->toMatchArray(['type' => 'text', 'sortable' => false, 'filterType' => 'set'])
        ->and($columns['settlements_count'])->toMatchArray(['type' => 'number', 'sortable' => true, 'filterable' => false])
        ->and($columns['skipped_count'])->toMatchArray(['type' => 'number', 'sortable' => true, 'filterable' => false])
        ->and($columns['created_by'])->toMatchArray(['type' => 'text', 'sortable' => true, 'filterType' => 'set']);
});

it('AC-018: rows carry the company of the run filters (null for all), the skipped count and the creator', function () {
    $acme = Company::factory()->create(['denomination' => 'Acme']);
    $creator = User::factory()->create(['name' => 'Anna Contabile']);
    $scoped = tableRun(['filters' => ['company_id' => $acme->id, 'referent_ids' => null], 'skipped' => skippedPairs(2), 'settlements_count' => 4, 'created_by' => $creator->id]);
    $all = tableRun(['filters' => ['company_id' => null], 'skipped' => [], 'settlements_count' => 1]);
    $unfiltered = tableRun(['filters' => null, 'skipped' => null]);

    $rows = collect(settlementTableRows(RUNS_DOMAIN)['items'])->keyBy('id');

    expect($rows[$scoped->id])->toMatchArray([
        'company' => 'Acme', 'skipped_count' => 2, 'settlements_count' => 4, 'created_by' => 'Anna Contabile',
        'period_from' => '2026-09-01', 'period_to' => '2026-09-30',
    ])
        ->and($rows[$scoped->id]['created_at'])->not->toBeNull()
        ->and($rows[$all->id])->toMatchArray(['company' => null, 'skipped_count' => 0, 'settlements_count' => 1])
        ->and($rows[$unfiltered->id])->toMatchArray(['company' => null, 'skipped_count' => 0]);
});

it('AC-018: default sort is created_at desc then id desc, and the sortable columns sort server side', function () {
    $old = tableRun(['created_at' => '2026-01-01 10:00:00', 'settlements_count' => 5, 'skipped' => skippedPairs(1), 'period_to' => '2026-06-30']);
    $new = tableRun(['created_at' => '2026-03-01 10:00:00', 'settlements_count' => 1, 'skipped' => skippedPairs(3), 'period_to' => '2026-01-31']);
    $tie = tableRun(['created_at' => '2026-03-01 10:00:00', 'settlements_count' => 3, 'skipped' => [], 'period_to' => '2026-03-31']);
    $sorted = static fn (string $column, string $direction): array => settlementTableIds(RUNS_DOMAIN, ['sortModel' => [['colId' => $column, 'sort' => $direction]]]);

    expect(settlementTableIds(RUNS_DOMAIN))->toBe([$tie->id, $new->id, $old->id])
        ->and($sorted('settlements_count', 'desc'))->toBe([$old->id, $tie->id, $new->id])
        ->and($sorted('skipped_count', 'desc'))->toBe([$new->id, $old->id, $tie->id])
        ->and($sorted('skipped_count', 'asc'))->toBe([$tie->id, $old->id, $new->id])
        ->and($sorted('period_to', 'asc'))->toBe([$new->id, $tie->id, $old->id]);

    foreach (['created_at', 'period_from', 'created_by'] as $column) {
        expect($sorted($column, 'asc'))->toHaveCount(3);
    }

    $this->postJson('/api/tables/commission-settlement-runs/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'company', 'sort' => 'asc']]])->assertUnprocessable();
});

it('AC-018: filters by company (null means all), creator and period dates', function () {
    $acme = Company::factory()->create(['denomination' => 'Acme']);
    $anna = User::factory()->create(['name' => 'Anna']);
    $scoped = tableRun(['filters' => ['company_id' => $acme->id], 'created_by' => $anna->id, 'period_from' => '2026-02-01', 'period_to' => '2026-02-28']);
    $all = tableRun(['filters' => ['company_id' => null], 'period_from' => '2026-05-01', 'period_to' => '2026-05-31']);
    $set = static fn (array $values): array => ['filterType' => 'set', 'values' => $values];
    $ids = static fn (array $filter): array => settlementTableIds(RUNS_DOMAIN, ['filterModel' => $filter, 'sortModel' => [['colId' => 'id', 'sort' => 'asc']]]);

    expect($ids(['company' => $set(['Acme'])]))->toBe([$scoped->id])
        ->and($ids(['company' => $set([null])]))->toBe([$all->id])
        ->and($ids(['company' => $set(['Acme', null])]))->toBe([$scoped->id, $all->id])
        ->and($ids(['created_by' => $set(['Anna'])]))->toBe([$scoped->id])
        ->and($ids(['period_from' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-03-01 00:00:00']]))->toBe([$all->id])
        ->and($ids(['period_to' => ['filterType' => 'date', 'type' => 'lessThan', 'dateFrom' => '2026-03-01 00:00:00']]))->toBe([$scoped->id]);

    $values = $this->postJson('/api/tables/commission-settlement-runs/values', ['columnId' => 'company', 'filterModel' => []])->assertOk()->json('data.values');

    expect($values)->toBe([null, 'Acme']);
});

it('AC-018: a user without commission-settlements.viewAny gets 403 on columns, rows and values', function () {
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'create', 'export']));

    $this->getJson('/api/tables/commission-settlement-runs/columns')->assertForbidden();
    $this->postJson('/api/tables/commission-settlement-runs/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->postJson('/api/tables/commission-settlement-runs/values', ['columnId' => 'company', 'filterModel' => []])->assertForbidden();
});

it('AC-018: exports the runs with commission-settlements.export and refuses without it', function () {
    Storage::fake((string) config('exports.disk'));
    $acme = Company::factory()->create(['denomination' => 'Acme Export']);
    tableRun(['filters' => ['company_id' => $acme->id]]);
    tableRun(['filters' => null]);
    $body = ['format' => 'csv', 'columns' => [['colId' => 'company', 'header' => 'Company'], ['colId' => 'skipped_count', 'header' => 'Skipped']]];

    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'export']));
    $this->postJson('/api/exports/commission-settlement-runs', $body)->assertSuccessful();
    $run = ExportRun::query()->latest('id')->firstOrFail();

    expect($run->row_count)->toBe(2)->and(Storage::disk((string) config('exports.disk'))->get($run->file_path))->toContain('Acme Export');

    Queue::fake();
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny']));
    $this->postJson('/api/exports/commission-settlement-runs', $body)->assertForbidden();
});

it('AC-018: the history is read-only: no inline edit and no bulk delete, even for a super-admin', function () {
    $run = tableRun();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($admin);

    $this->patchJson("/api/tables/commission-settlement-runs/rows/{$run->id}", ['column' => 'settlements_count', 'value' => 9])->assertStatus(403);
    $this->postJson('/api/tables/commission-settlement-runs/bulk-delete', ['ids' => [$run->id]])->assertOk()
        ->assertJsonPath('data.deleted', 0)->assertJsonPath('data.failed.0.reason', 'forbidden');

    expect(CommissionSettlementRun::query()->count())->toBe(1);
});
