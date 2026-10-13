<?php

use App\Models\CommissionAccrual;
use App\Models\ExportRun;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

const LEDGER_ROWS_URL = '/api/tables/commission-accruals/rows';

/**
 * @param  array<int, string>  $abilities
 */
function ledgerTableUser(array $abilities): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("commission-accruals.{$ability}", 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function ledgerRows(array $body = []): array
{
    return test()->postJson(LEDGER_ROWS_URL, $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function ledgerAccrualOf(array $attributes = []): CommissionAccrual
{
    return CommissionAccrual::factory()->create($attributes);
}

beforeEach(function () {
    Sanctum::actingAs(ledgerTableUser(['viewAny', 'view']));
});

it('AC-017: exposes the contract columns, the badge enum keys and the row grouping config', function () {
    $data = $this->getJson('/api/tables/commission-accruals/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->keys()->all())->toBe([
        'id', 'accrued_on', 'accrued_month', 'recipient', 'recipient_kind', 'recipient_role', 'type', 'invoice',
        'customer', 'quote', 'product', 'base_amount', 'amount', 'settled_externally_on', 'status',
    ])
        ->and($data['row_grouping'])->toBe([
            'enabled' => true,
            'max_depth' => 3,
            'columns' => ['recipient', 'recipient_role', 'accrued_month', 'type', 'customer'],
        ])
        ->and($columns['amount']['aggFunc'])->toBe('sum')
        ->and($columns['accrued_month']['visible'])->toBeFalse()
        ->and($columns['accrued_month']['sortable'])->toBeFalse()
        ->and($columns['status']['sortable'])->toBeFalse()
        ->and($columns['status']['options'])->toBe(['open', 'settled_externally'])
        ->and($columns['recipient_role']['options'])->toBe(['COMMERCIAL', 'REPORTER', 'SUPERVISOR'])
        ->and($columns['recipient_kind']['enumKey'])->toBe('commission_recipient_kind')
        ->and($columns['recipient_role']['enumKey'])->toBe('commission_recipient_role')
        ->and($columns['type']['enumKey'])->toBe('commission_accrual_type')
        ->and($columns['status']['enumKey'])->toBe('commission_accrual_status')
        ->and($columns['recipient']['label'])->toBe('commissionAccruals.columns.recipient')
        ->and($data['actions'])->toBe([]);
});

it('AC-017: rows carry the contract fields with recipient, invoice, customer, quote and product', function () {
    $customer = Registry::factory()->create(['name' => 'Cliente Uno']);
    $invoice = installmentInvoice($customer, null, null, ['number' => 12, 'year' => 2026]);
    $line = QuoteLine::factory()->create(['product_id' => Product::factory()->create(['name' => 'Corso Base'])->id]);
    $referent = Referent::factory()->create(['name' => 'Mario Agente']);
    $accrual = ledgerAccrualOf([
        'invoice_id' => $invoice->id, 'quote_line_id' => $line->id, 'recipient_id' => $referent->id,
        'accrued_on' => '2026-03-15', 'base_amount' => '100.00', 'amount' => '50.00',
    ]);

    $row = ledgerRows()['items'][0];

    expect($row)->toMatchArray([
        'id' => $accrual->id, 'invoice_id' => $invoice->id, 'quote_id' => $line->quote_id,
        'recipient_type' => 'referent', 'recipient_id' => $referent->id,
        'accrued_on' => '2026-03-15', 'accrued_month' => '2026-03', 'recipient' => 'Mario Agente',
        'recipient_kind' => 'referent', 'recipient_role' => 'COMMERCIAL', 'type' => 'ACCRUAL',
        'invoice' => '12/2026', 'customer' => 'Cliente Uno', 'quote' => $line->quote->code, 'product' => 'Corso Base',
        'base_amount' => '100.00', 'amount' => '50.00', 'settled_externally_on' => null, 'status' => 'open',
    ]);
});

it('AC-017: a user recipient and a movement whose invoice and quote line are gone are still listed', function () {
    $user = User::factory()->create(['name' => 'Giulia Utente']);
    ledgerAccrualOf(['recipient_type' => 'user', 'recipient_id' => $user->id, 'settled_externally_on' => '2026-02-01']);

    $row = ledgerRows()['items'][0];

    expect($row)->toMatchArray(['recipient' => 'Giulia Utente', 'recipient_kind' => 'user', 'status' => 'settled_externally',
        'invoice' => null, 'customer' => null, 'quote' => null, 'quote_id' => null, 'product' => null]);
});

it('AC-017: default sort is accrued_on desc then id desc, and every sortable column sorts server side', function () {
    $a = ledgerAccrualOf(['accrued_on' => '2026-01-10', 'amount' => '30.00']);
    $b = ledgerAccrualOf(['accrued_on' => '2026-03-10', 'amount' => '10.00']);
    $c = ledgerAccrualOf(['accrued_on' => '2026-03-10', 'amount' => '20.00']);
    $ids = static fn (array $body): array => array_column(ledgerRows($body)['items'], 'id');

    expect($ids([]))->toBe([$c->id, $b->id, $a->id])
        ->and($ids(['sortModel' => [['colId' => 'amount', 'sort' => 'asc']]]))->toBe([$b->id, $c->id, $a->id])
        ->and($ids(['sortModel' => [['colId' => 'accrued_on', 'sort' => 'asc']]]))->toBe([$a->id, $b->id, $c->id]);

    foreach (['accrued_on', 'recipient', 'recipient_kind', 'recipient_role', 'type', 'invoice', 'customer', 'quote', 'product', 'base_amount', 'amount', 'settled_externally_on'] as $column) {
        expect($ids(['sortModel' => [['colId' => $column, 'sort' => 'desc']]]))->toHaveCount(3);
    }

    $this->postJson(LEDGER_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'status', 'sort' => 'asc']]])->assertUnprocessable();
});

it('AC-017: filters on every declared column', function () {
    $mario = Referent::factory()->create(['name' => 'Mario']);
    $luigi = Referent::factory()->create(['name' => 'Luigi']);
    $invoice = installmentInvoice(Registry::factory()->create(['name' => 'Acme']), null, null, ['number' => 7, 'year' => 2026]);
    $open = ledgerAccrualOf(['recipient_id' => $mario->id, 'invoice_id' => $invoice->id, 'accrued_on' => '2026-03-10', 'amount' => '50.00', 'base_amount' => '80.00']);
    $reversal = ledgerAccrualOf(['recipient_id' => $luigi->id, 'type' => 'REVERSAL', 'recipient_role' => 'SUPERVISOR', 'accrued_on' => '2026-04-02', 'amount' => '-50.00', 'settled_externally_on' => '2026-05-01']);
    $ids = static fn (array $filter): array => array_column(ledgerRows(['filterModel' => $filter])['items'], 'id');
    $set = static fn (array $values): array => ['filterType' => 'set', 'values' => $values];

    expect($ids(['status' => $set(['open'])]))->toBe([$open->id])
        ->and($ids(['status' => $set(['settled_externally'])]))->toBe([$reversal->id])
        ->and($ids(['status' => $set(['open', 'settled_externally'])]))->toHaveCount(2)
        ->and($ids(['type' => $set(['REVERSAL'])]))->toBe([$reversal->id])
        ->and($ids(['recipient_role' => $set(['SUPERVISOR'])]))->toBe([$reversal->id])
        ->and($ids(['recipient_kind' => $set(['user'])]))->toBe([])
        ->and($ids(['recipient' => $set(['Mario'])]))->toBe([$open->id])
        ->and($ids(['customer' => $set(['Acme'])]))->toBe([$open->id])
        ->and($ids(['accrued_month' => $set(['2026-04'])]))->toBe([$reversal->id])
        ->and($ids(['invoice' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '7/2026']]))->toBe([$open->id])
        ->and($ids(['amount' => ['filterType' => 'number', 'type' => 'lessThan', 'filter' => 0]]))->toBe([$reversal->id])
        ->and($ids(['base_amount' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 80]]))->toBe([$open->id]);

    $values = $this->postJson('/api/tables/commission-accruals/values', ['columnId' => 'recipient', 'filterModel' => []])->assertOk()->json('data.values');
    $months = $this->postJson('/api/tables/commission-accruals/values', ['columnId' => 'accrued_month', 'filterModel' => []])->assertOk()->json('data.values');
    $status = $this->postJson('/api/tables/commission-accruals/values', ['columnId' => 'status', 'filterModel' => []])->assertOk()->json('data.values');

    expect($values)->toEqualCanonicalizing(['Mario', 'Luigi'])
        ->and($months)->toBe(['2026-03', '2026-04'])
        ->and($status)->toBe(['open', 'settled_externally']);
});

it('AC-017: groups by recipient with the child count and the sum of the amount, then opens the leaves', function () {
    $mario = Referent::factory()->create(['name' => 'Mario']);
    $luigi = Referent::factory()->create(['name' => 'Luigi']);
    $first = ledgerAccrualOf(['recipient_id' => $mario->id, 'amount' => '50.00']);
    ledgerAccrualOf(['recipient_id' => $mario->id, 'amount' => '25.50']);
    ledgerAccrualOf(['recipient_id' => $mario->id, 'amount' => '-10.00', 'type' => 'REVERSAL']);
    ledgerAccrualOf(['recipient_id' => $luigi->id, 'amount' => '7.00']);

    $body = ledgerRows(['rowGroupCols' => ['recipient'], 'groupKeys' => []]);

    expect($body['items'])->toBe([
        ['group' => true, 'column' => 'recipient', 'key' => "referent:{$luigi->id}", 'label' => 'Luigi', 'child_count' => 1, 'aggregates' => ['amount' => '7.00']],
        ['group' => true, 'column' => 'recipient', 'key' => "referent:{$mario->id}", 'label' => 'Mario', 'child_count' => 3, 'aggregates' => ['amount' => '65.50']],
    ])
        ->and($body['pagination']['total'])->toBe(2)
        ->and($body['meta']['aggregates'])->toBe(['amount' => '72.50']);

    $leaves = ledgerRows(['rowGroupCols' => ['recipient'], 'groupKeys' => ["referent:{$mario->id}"]]);

    expect($leaves['items'])->toHaveCount(3)->and(array_column($leaves['items'], 'id'))->toContain($first->id)
        ->and($leaves['items'][0])->not->toHaveKey('group');
});

it('AC-017: groups by accrued_month then by type, sending missing values to __null__', function () {
    ledgerAccrualOf(['accrued_on' => '2026-03-02', 'amount' => '10.00']);
    ledgerAccrualOf(['accrued_on' => '2026-03-20', 'amount' => '5.00', 'type' => 'REVERSAL']);
    ledgerAccrualOf(['accrued_on' => '2026-04-01', 'amount' => '1.00']);

    $months = collect(ledgerRows(['rowGroupCols' => ['accrued_month']])['items'])->keyBy('key');
    $types = collect(ledgerRows(['rowGroupCols' => ['accrued_month', 'type'], 'groupKeys' => ['2026-03']])['items'])->keyBy('key');
    $customers = ledgerRows(['rowGroupCols' => ['customer']])['items'];

    expect($months->keys()->all())->toBe(['2026-03', '2026-04'])
        ->and($months['2026-03'])->toMatchArray(['child_count' => 2, 'aggregates' => ['amount' => '15.00']])
        ->and($types->keys()->all())->toBe(['ACCRUAL', 'REVERSAL'])
        ->and($types['REVERSAL']['aggregates'])->toBe(['amount' => '5.00'])
        ->and($customers[0]['key'])->toBe('__null__');
});

it('AC-017: invalid grouping requests are 422', function (array $body) {
    $this->postJson(LEDGER_ROWS_URL, $body + ['startRow' => 0, 'endRow' => 10])->assertUnprocessable();
})->with([
    'column not groupable' => [['rowGroupCols' => ['invoice']]],
    'unknown column' => [['rowGroupCols' => ['password']]],
    'more than 3 levels' => [['rowGroupCols' => ['recipient', 'recipient_role', 'accrued_month', 'type']]],
]);

it('AC-017: a role that cannot see the amounts never receives them in config, rows, aggregates, groups or export, and cannot sort, filter or group on them', function () {
    $user = ledgerTableUser(['viewAny', 'view', 'export']);
    $role = Role::create(['name' => 'no-accrual-amounts', 'guard_name' => 'web']);
    $user->assignRole($role);
    $hidden = ['base_amount', 'amount'];
    foreach ($hidden as $field) {
        $role->fieldPermissions()->create(['resource' => 'commission-accruals', 'field' => $field, 'visible' => false, 'editable' => false, 'required' => false]);
    }
    Sanctum::actingAs($user);
    ledgerAccrualOf();

    $data = $this->getJson('/api/tables/commission-accruals/columns')->assertOk()->json('data');
    expect(array_column($data['columns'], 'id'))->not->toContain(...$hidden)
        ->and($data['row_grouping']['columns'])->toContain('recipient')
        ->and(collect($data['columns'])->pluck('aggFunc')->filter()->all())->toBe([]);

    $body = ledgerRows();
    expect($body['items'][0])->not->toHaveKeys($hidden)->and($body)->not->toHaveKey('meta');

    $group = ledgerRows(['rowGroupCols' => ['recipient'], 'groupKeys' => []]);
    expect($group['items'][0]['aggregates'])->toBe([])->and($group)->not->toHaveKey('meta');

    $this->postJson(LEDGER_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'sortModel' => [['colId' => 'amount', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson(LEDGER_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'filterModel' => ['base_amount' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]]])->assertUnprocessable();
    $this->postJson(LEDGER_ROWS_URL, ['startRow' => 0, 'endRow' => 10, 'rowGroupCols' => ['amount']])->assertUnprocessable();
    $this->postJson('/api/exports/commission-accruals', ['format' => 'csv', 'columns' => [['colId' => 'amount', 'header' => 'Amount']]])->assertUnprocessable();
});

it('AC-017: the ledger has no write endpoint: PATCH row is 403 and bulk-delete deletes nothing, even for a super-admin', function () {
    $accrual = ledgerAccrualOf(['amount' => '50.00']);

    $this->patchJson("/api/tables/commission-accruals/rows/{$accrual->id}", ['column' => 'amount', 'value' => '1.00'])->assertStatus(403);
    $this->postJson('/api/tables/commission-accruals/bulk-delete', ['ids' => [$accrual->id]])->assertOk()
        ->assertJsonPath('data.deleted', 0)->assertJsonPath('data.failed.0.reason', 'forbidden');
    $this->putJson("/api/commission-accruals/{$accrual->id}", ['amount' => '1.00'])->assertStatus(404);
    $this->deleteJson("/api/commission-accruals/{$accrual->id}")->assertStatus(404);

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($admin);

    $this->patchJson("/api/tables/commission-accruals/rows/{$accrual->id}", ['column' => 'amount', 'value' => '1.00'])->assertStatus(403);
    $this->postJson('/api/tables/commission-accruals/bulk-delete', ['ids' => [$accrual->id]])->assertOk()
        ->assertJsonPath('data.deleted', 0)->assertJsonPath('data.failed.0.reason', 'forbidden');

    expect($accrual->fresh()->amount)->toBe('50.00');
});

it('AC-017: a user without commission-accruals.viewAny gets 403 on columns, rows and values', function () {
    Sanctum::actingAs(ledgerTableUser(['view']));

    $this->getJson('/api/tables/commission-accruals/columns')->assertForbidden();
    $this->postJson(LEDGER_ROWS_URL, ['startRow' => 0, 'endRow' => 10])->assertForbidden();
    $this->postJson('/api/tables/commission-accruals/values', ['columnId' => 'status', 'filterModel' => []])->assertForbidden();
});

it('AC-017: exports the filtered movements as csv and refuses without commission-accruals.export', function () {
    Storage::fake((string) config('exports.disk'));
    Sanctum::actingAs(ledgerTableUser(['viewAny', 'view', 'export']));
    ledgerAccrualOf(['amount' => '50.00']);
    ledgerAccrualOf(['amount' => '5.00', 'settled_externally_on' => '2026-01-01']);

    $this->postJson('/api/exports/commission-accruals', [
        'format' => 'csv',
        'columns' => [['colId' => 'recipient', 'header' => 'Recipient'], ['colId' => 'amount', 'header' => 'Amount']],
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['open']]],
    ])->assertSuccessful();

    expect(ExportRun::query()->latest('id')->firstOrFail()->row_count)->toBe(1);

    Queue::fake();
    Sanctum::actingAs(ledgerTableUser(['viewAny', 'view']));
    $this->postJson('/api/exports/commission-accruals', ['format' => 'csv', 'columns' => [['colId' => 'recipient', 'header' => 'Recipient']]])->assertForbidden();
});

it('AC-017: a quote joined through the quote line is reachable by the quote text filter', function () {
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $match = ledgerAccrualOf(['quote_line_id' => $line->id]);
    ledgerAccrualOf();

    $ids = array_column(ledgerRows(['filterModel' => ['quote' => ['filterType' => 'text', 'type' => 'equals', 'filter' => $quote->code]]])['items'], 'id');

    expect($ids)->toBe([$match->id]);
});
