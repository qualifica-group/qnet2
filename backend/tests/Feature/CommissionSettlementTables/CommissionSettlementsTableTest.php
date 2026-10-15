<?php

use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\ExportRun;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const SETTLEMENTS_DOMAIN = 'commission-settlements';

const SETTLEMENT_AMOUNT_COLUMNS = ['taxable_amount', 'vat_amount', 'withholding_amount', 'enasarco_agent_amount', 'enasarco_principal_amount', 'net_payable'];

/**
 * @param  array<string, mixed>  $attributes
 */
function tableSettlement(array $attributes = []): CommissionSettlement
{
    return CommissionSettlement::factory()->create($attributes);
}

beforeEach(function () {
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'view']));
});

it('AC-018: exposes the contract columns, the status enum key, the filters and the row grouping config', function () {
    $data = $this->getJson('/api/tables/commission-settlements/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe([
        'id', 'code', 'status', 'referent', 'company', 'period_from', 'period_to', 'period_month', ...SETTLEMENT_AMOUNT_COLUMNS, 'issued_at', 'document_number', 'payment_status',
    ])
        ->and($data['row_grouping'])->toBe(['enabled' => true, 'max_depth' => 3, 'columns' => ['status', 'referent', 'company', 'period_month']])
        ->and($data['defaultSort'])->toBe([['columnId' => 'period_to', 'direction' => 'desc'], ['columnId' => 'id', 'direction' => 'desc']])
        ->and($columns['code'])->toMatchArray(['type' => 'text', 'sortable' => true, 'filterType' => 'text', 'label' => 'commissionSettlements.columns.code'])
        ->and($columns['status'])->toMatchArray(['type' => 'badge', 'sortable' => true, 'filterType' => 'set', 'enumKey' => 'commission_settlement_status', 'options' => ['DRAFT', 'ISSUED', 'DOCUMENT_RECEIVED', 'APPROVED', 'CANCELLED']])
        ->and($columns['document_number'])->toMatchArray(['type' => 'text', 'sortable' => true, 'filterType' => 'text'])
        ->and($columns['payment_status'])->toMatchArray(['type' => 'badge', 'sortable' => false, 'filterType' => 'set', 'options' => ['paid', 'not_due', 'overdue', 'seriously_overdue']])
        ->and($columns['period_month'])->toMatchArray(['visible' => false, 'sortable' => false, 'filterType' => 'set'])
        ->and($columns['taxable_amount'])->toMatchArray(['filterType' => 'number', 'aggFunc' => 'sum'])
        ->and($columns['net_payable'])->toMatchArray(['filterType' => 'number', 'aggFunc' => 'sum'])
        ->and($columns['vat_amount']['filterable'])->toBeFalse()
        ->and($columns['issued_at'])->toMatchArray(['type' => 'date', 'filterType' => 'date'])
        ->and(collect($columns)->filter(fn (array $column): bool => $column['aggFunc'] === 'sum')->keys()->all())->toBe(SETTLEMENT_AMOUNT_COLUMNS)
        ->and(array_column($data['actions'], 'key'))->toBe(['view']);
});

it('AC-018: rows carry the contract fields, the code only once issued, and string amounts', function () {
    $company = Company::factory()->create(['denomination' => 'Acme Spa']);
    $referent = Referent::factory()->create(['name' => 'Mario Agente']);
    $draft = tableSettlement(['company_id' => $company->id, 'referent_id' => $referent->id, 'taxable_amount' => '1000.00', 'net_payable' => '1020.00']);
    $issued = CommissionSettlement::factory()->issued(7, 2026)->create(['company_id' => $company->id, 'referent_id' => $referent->id, 'period_to' => '2026-10-31']);

    $rows = collect(settlementTableRows(SETTLEMENTS_DOMAIN)['items'])->keyBy('id');

    expect($rows[$draft->id])->toMatchArray([
        'referent_id' => $referent->id, 'company_id' => $company->id, 'code' => null, 'status' => 'DRAFT',
        'referent' => 'Mario Agente', 'company' => 'Acme Spa', 'period_from' => '2026-09-01', 'period_to' => '2026-09-30',
        'period_month' => '2026-09', 'taxable_amount' => '1000.00', 'net_payable' => '1020.00', 'issued_at' => null,
    ])
        ->and($rows[$issued->id])->toMatchArray(['code' => '7/2026', 'status' => 'ISSUED', 'period_month' => '2026-10'])
        ->and($rows[$issued->id]['issued_at'])->not->toBeNull();
});

it('AC-018: the row abilities are the policy ability AND the state of the statement', function () {
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'update', 'issue', 'cancel', 'delete']));
    $draft = tableSettlement();
    $issued = CommissionSettlement::factory()->issued()->create();
    $cancelled = CommissionSettlement::factory()->cancelled()->create();

    $rows = collect(settlementTableRows(SETTLEMENTS_DOMAIN)['items'])->keyBy('id');

    expect($rows[$draft->id]['abilities'])->toBe(['recalculate' => true, 'issue' => true, 'cancel' => false, 'delete' => true, 'receive_document' => false, 'remove_document' => false, 'approve' => false])
        ->and($rows[$issued->id]['abilities'])->toBe(['recalculate' => false, 'issue' => false, 'cancel' => true, 'delete' => false, 'receive_document' => false, 'remove_document' => false, 'approve' => false])
        ->and($rows[$cancelled->id]['abilities'])->toBe(['recalculate' => false, 'issue' => false, 'cancel' => false, 'delete' => false, 'receive_document' => false, 'remove_document' => false, 'approve' => false]);

    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'issue']));
    $rows = collect(settlementTableRows(SETTLEMENTS_DOMAIN)['items'])->keyBy('id');

    expect($rows[$draft->id]['abilities'])->toBe(['recalculate' => false, 'issue' => true, 'cancel' => false, 'delete' => false, 'receive_document' => false, 'remove_document' => false, 'approve' => false])
        ->and($rows[$issued->id]['abilities'])->toBe(['recalculate' => false, 'issue' => false, 'cancel' => false, 'delete' => false, 'receive_document' => false, 'remove_document' => false, 'approve' => false]);
});

it('AC-018: default sort is period_to desc then id desc and every sortable column sorts server side', function () {
    $a = tableSettlement(['period_to' => '2026-07-31']);
    $b = tableSettlement(['period_to' => '2026-09-30']);
    $c = tableSettlement(['period_to' => '2026-09-30']);

    expect(settlementTableIds(SETTLEMENTS_DOMAIN))->toBe([$c->id, $b->id, $a->id]);

    $nine = CommissionSettlement::factory()->issued(9, 2026)->create();
    $ten = CommissionSettlement::factory()->issued(10, 2026)->create();
    $early = CommissionSettlement::factory()->issued(3, 2025)->create();
    $codeOrder = array_values(array_intersect(settlementTableIds(SETTLEMENTS_DOMAIN, ['sortModel' => [['colId' => 'code', 'sort' => 'asc']]]), [$early->id, $nine->id, $ten->id]));

    expect($codeOrder)->toBe([$early->id, $nine->id, $ten->id]);

    $b->update(['net_payable' => '5.00']);
    $c->update(['net_payable' => '9.00']);
    $a->update(['net_payable' => '7.00']);
    $byNet = settlementTableIds(SETTLEMENTS_DOMAIN, ['sortModel' => [['colId' => 'net_payable', 'sort' => 'desc']]]);

    expect(array_slice($byNet, 0, 3))->toBe([$c->id, $a->id, $b->id]);

    foreach (['code', 'status', 'referent', 'company', 'period_from', 'period_to', ...SETTLEMENT_AMOUNT_COLUMNS, 'issued_at'] as $column) {
        expect(settlementTableIds(SETTLEMENTS_DOMAIN, ['sortModel' => [['colId' => $column, 'sort' => 'asc']]]))->toHaveCount(6);
    }

    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'period_month', 'sort' => 'asc']]])->assertUnprocessable();
});

it('AC-018: filters on every filterable column', function () {
    $acme = Company::factory()->create(['denomination' => 'Acme']);
    $mario = Referent::factory()->create(['name' => 'Mario']);
    $draft = tableSettlement(['company_id' => $acme->id, 'referent_id' => $mario->id, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30', 'taxable_amount' => '500.00', 'net_payable' => '400.00']);
    $issued = CommissionSettlement::factory()->issued(12, 2026)->create(['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'taxable_amount' => '900.00', 'net_payable' => '800.00']);
    $cancelled = CommissionSettlement::factory()->cancelled()->create(['period_from' => '2026-11-01', 'period_to' => '2026-11-30']);
    $set = static fn (array $values): array => ['filterType' => 'set', 'values' => $values];
    $ids = static fn (array $filter): array => settlementTableIds(SETTLEMENTS_DOMAIN, ['filterModel' => $filter, 'sortModel' => [['colId' => 'id', 'sort' => 'asc']]]);

    expect($ids(['status' => $set(['DRAFT'])]))->toBe([$draft->id])
        ->and($ids(['status' => $set(['ISSUED', 'CANCELLED'])]))->toBe([$issued->id, $cancelled->id])
        ->and($ids(['referent' => $set(['Mario'])]))->toBe([$draft->id])
        ->and($ids(['company' => $set(['Acme'])]))->toBe([$draft->id])
        ->and($ids(['period_month' => $set(['2026-10'])]))->toBe([$issued->id])
        ->and($ids(['code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '12/2026']]))->toBe([$issued->id])
        ->and($ids(['code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '/']]))->toBe([$issued->id])
        ->and($ids(['taxable_amount' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 600]]))->toBe([$issued->id])
        ->and($ids(['net_payable' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 400]]))->toBe([$draft->id])
        ->and($ids(['period_to' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-10-15 00:00:00']]))->toBe([$issued->id, $cancelled->id])
        ->and($ids(['issued_at' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2000-01-01 00:00:00']]))->toBe([$issued->id]);

    $values = fn (string $column): array => $this->postJson('/api/tables/commission-settlements/values', ['columnId' => $column, 'filterModel' => []])->assertOk()->json('data.values');

    expect($values('status'))->toBe(['DRAFT', 'ISSUED', 'DOCUMENT_RECEIVED', 'APPROVED', 'CANCELLED'])
        ->and($values('referent'))->toContain('Mario')
        ->and($values('company'))->toContain('Acme')
        ->and($values('period_month'))->toBe(['2026-09', '2026-10', '2026-11']);
});

it('AC-018: groups on up to three levels with the child count and the sum of every amount', function () {
    $acme = Company::factory()->create(['denomination' => 'Acme']);
    $beta = Company::factory()->create(['denomination' => 'Beta']);
    $mario = Referent::factory()->create(['name' => 'Mario']);
    $luigi = Referent::factory()->create(['name' => 'Luigi']);
    $amounts = static fn (string $taxable, string $net): array => [
        'taxable_amount' => $taxable, 'vat_amount' => '10.00', 'withholding_amount' => '1.00',
        'enasarco_agent_amount' => '2.00', 'enasarco_principal_amount' => '2.00', 'net_payable' => $net,
    ];
    tableSettlement(['referent_id' => $mario->id, 'company_id' => $acme->id, 'period_to' => '2026-09-30', ...$amounts('100.00', '90.00')]);
    tableSettlement(['referent_id' => $mario->id, 'company_id' => $acme->id, 'period_to' => '2026-10-31', ...$amounts('50.50', '40.25')]);
    tableSettlement(['referent_id' => $mario->id, 'company_id' => $beta->id, 'period_to' => '2026-09-30', ...$amounts('20.00', '15.00')]);
    CommissionSettlement::factory()->issued()->create(['referent_id' => $luigi->id, 'company_id' => $acme->id, 'period_to' => '2026-09-30', ...$amounts('7.00', '6.00')]);

    $byStatus = settlementTableRows(SETTLEMENTS_DOMAIN, ['rowGroupCols' => ['status'], 'groupKeys' => []]);

    expect($byStatus['items'])->toBe([
        ['group' => true, 'column' => 'status', 'key' => 'DRAFT', 'label' => 'DRAFT', 'child_count' => 3, 'aggregates' => [
            'taxable_amount' => '170.50', 'vat_amount' => '30.00', 'withholding_amount' => '3.00',
            'enasarco_agent_amount' => '6.00', 'enasarco_principal_amount' => '6.00', 'net_payable' => '145.25',
        ]],
        ['group' => true, 'column' => 'status', 'key' => 'ISSUED', 'label' => 'ISSUED', 'child_count' => 1, 'aggregates' => [
            'taxable_amount' => '7.00', 'vat_amount' => '10.00', 'withholding_amount' => '1.00',
            'enasarco_agent_amount' => '2.00', 'enasarco_principal_amount' => '2.00', 'net_payable' => '6.00',
        ]],
    ])
        ->and($byStatus['pagination']['total'])->toBe(2)
        ->and($byStatus['meta']['aggregates']['taxable_amount'])->toBe('177.50');

    $third = settlementTableRows(SETTLEMENTS_DOMAIN, [
        'rowGroupCols' => ['status', 'referent', 'company'],
        'groupKeys' => ['DRAFT', (string) $mario->id],
    ]);

    expect(collect($third['items'])->pluck('label', 'key')->all())->toBe(["{$acme->id}" => 'Acme', "{$beta->id}" => 'Beta'])
        ->and($third['items'][0]['aggregates']['taxable_amount'])->toBe('150.50');

    $leaves = settlementTableRows(SETTLEMENTS_DOMAIN, [
        'rowGroupCols' => ['referent', 'company', 'period_month'],
        'groupKeys' => [(string) $mario->id, (string) $acme->id, '2026-10'],
    ]);

    expect($leaves['items'])->toHaveCount(1)->and($leaves['items'][0])->not->toHaveKey('group')->and($leaves['items'][0]['taxable_amount'])->toBe('50.50');
});

it('AC-018: a fourth grouping level is refused', function () {
    $this->postJson('/api/tables/commission-settlements/rows', [
        'startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['status', 'referent', 'company', 'period_month'],
    ])->assertUnprocessable();
    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['code']])->assertUnprocessable();
    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['net_payable']])->assertUnprocessable();
});

it('AC-017: a role that cannot see the amounts never receives them in config, rows, aggregates, groups or export, and cannot sort, filter or group on them', function () {
    $user = commissionSettlementUserWith(['viewAny', 'view', 'export']);
    hideSettlementFields($user, ['net_payable', 'vat_amount']);
    Sanctum::actingAs($user);
    tableSettlement(['net_payable' => '10.00']);
    $hidden = ['net_payable', 'vat_amount'];

    $data = $this->getJson('/api/tables/commission-settlements/columns')->assertOk()->json('data');
    $aggregated = collect($data['columns'])->filter(fn (array $column): bool => $column['aggFunc'] === 'sum')->pluck('id')->all();

    expect(array_column($data['columns'], 'id'))->not->toContain(...$hidden)->and(array_column($data['filters'], 'columnId'))->not->toContain(...$hidden)
        ->and($aggregated)->toBe(['taxable_amount', 'withholding_amount', 'enasarco_agent_amount', 'enasarco_principal_amount']);

    $body = settlementTableRows(SETTLEMENTS_DOMAIN);
    expect($body['items'][0])->not->toHaveKeys($hidden)->toHaveKey('taxable_amount')
        ->and($body['meta']['aggregates'])->not->toHaveKeys($hidden);

    $group = settlementTableRows(SETTLEMENTS_DOMAIN, ['rowGroupCols' => ['status'], 'groupKeys' => []]);
    expect($group['items'][0]['aggregates'])->not->toHaveKeys($hidden)->toHaveKey('taxable_amount');

    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'net_payable', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['net_payable' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['net_payable']])->assertUnprocessable();
    $this->postJson('/api/exports/commission-settlements', ['format' => 'csv', 'columns' => [['colId' => 'net_payable', 'header' => 'Net']]])->assertUnprocessable();
});

it('AC-017: a role hiding every amount still lists the statements without aggregates', function () {
    $user = commissionSettlementUserWith(['viewAny']);
    hideSettlementFields($user, SETTLEMENT_AMOUNT_COLUMNS);
    Sanctum::actingAs($user);
    tableSettlement();

    $body = settlementTableRows(SETTLEMENTS_DOMAIN);
    $group = settlementTableRows(SETTLEMENTS_DOMAIN, ['rowGroupCols' => ['referent'], 'groupKeys' => []]);

    expect($body['items'][0])->not->toHaveKeys(SETTLEMENT_AMOUNT_COLUMNS)->and($body)->not->toHaveKey('meta')
        ->and($group['items'][0]['aggregates'])->toBe([]);
});

it('AC-018: a user without commission-settlements.viewAny gets 403 on columns, rows and values', function () {
    Sanctum::actingAs(commissionSettlementUserWith(['view', 'create']));

    $this->getJson('/api/tables/commission-settlements/columns')->assertForbidden();
    $this->postJson('/api/tables/commission-settlements/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->postJson('/api/tables/commission-settlements/values', ['columnId' => 'status', 'filterModel' => []])->assertForbidden();
});

it('AC-017: exports the filtered statements and refuses without commission-settlements.export', function () {
    Storage::fake((string) config('exports.disk'));
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'export']));
    tableSettlement(['referent_id' => Referent::factory()->create(['name' => 'Alfa Agent'])->id, 'taxable_amount' => '10.00']);
    CommissionSettlement::factory()->issued()->create();

    $this->postJson('/api/exports/commission-settlements', [
        'format' => 'csv',
        'columns' => [['colId' => 'referent', 'header' => 'Agent'], ['colId' => 'taxable_amount', 'header' => 'Taxable']],
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['DRAFT']]],
    ])->assertSuccessful();

    $run = ExportRun::query()->latest('id')->firstOrFail();

    expect($run->row_count)->toBe(1)->and(Storage::disk((string) config('exports.disk'))->get($run->file_path))->toContain('Alfa Agent');

    Queue::fake();
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'view']));
    $this->postJson('/api/exports/commission-settlements', ['format' => 'csv', 'columns' => [['colId' => 'referent', 'header' => 'Agent']]])->assertForbidden();
});

it('AC-018: the grid has no inline edit and no bulk delete, even for a super-admin', function () {
    $draft = tableSettlement(['notes' => 'keep']);

    $this->patchJson("/api/tables/commission-settlements/rows/{$draft->id}", ['column' => 'notes', 'value' => 'x'])->assertStatus(403);
    $this->postJson('/api/tables/commission-settlements/bulk-delete', ['ids' => [$draft->id]])->assertOk()
        ->assertJsonPath('data.deleted', 0)->assertJsonPath('data.failed.0.reason', 'forbidden');

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($admin);

    $this->patchJson("/api/tables/commission-settlements/rows/{$draft->id}", ['column' => 'notes', 'value' => 'x'])->assertStatus(403);
    $this->postJson('/api/tables/commission-settlements/bulk-delete', ['ids' => [$draft->id]])->assertOk()
        ->assertJsonPath('data.deleted', 0)->assertJsonPath('data.failed.0.reason', 'forbidden');

    expect($draft->fresh()->notes)->toBe('keep');
});
