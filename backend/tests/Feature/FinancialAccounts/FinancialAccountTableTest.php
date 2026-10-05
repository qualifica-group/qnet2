<?php

use App\Models\Company;
use App\Models\ExportRun;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-016: exposes id/name/iban/type/notes/company, with type as a set filter and company hidden', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny']));

    $data = $this->getJson('/api/tables/financial-accounts/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($data['resource'])->toBe('financial-accounts')
        ->and($data['searchable'])->toEqualCanonicalizing(['name', 'iban'])
        ->and($columns->keys()->all())->toBe(['id', 'name', 'iban', 'type', 'notes', 'company'])
        ->and($columns['name']['sortable'])->toBeTrue()
        ->and($columns['iban']['sortable'])->toBeTrue()
        ->and($columns['type']['type'])->toBe('badge')
        ->and($columns['type']['filterType'])->toBe('set')
        ->and($columns['type']['options'])->toBe(['bank_account', 'card', 'cash'])
        ->and($columns['company']['visible'])->toBeFalse()
        ->and($columns['company']['filterable'])->toBeTrue()
        ->and(collect($data['filters'])->firstWhere('columnId', 'type')['options'])->toBe(['bank_account', 'card', 'cash']);
});

it('AC-016: quick-search covers name and iban, the type set filter narrows, an undeclared sort is ignored', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny']));
    $bank = FinancialAccount::factory()->bankAccount()->create(['name' => 'Alfa Bank']);
    FinancialAccount::factory()->card()->create(['name' => 'Beta Card']);
    FinancialAccount::factory()->cash()->create(['name' => 'Gamma Cash']);

    $names = fn (array $body) => collect($this->postJson('/api/tables/financial-accounts/rows', $body + ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->pluck('name')->all();

    expect($names(['search' => 'alfa']))->toBe(['Alfa Bank'])
        ->and($names(['search' => substr($bank->iban, 0, 12)]))->toBe(['Alfa Bank'])
        ->and($names(['filterModel' => ['type' => ['filterType' => 'set', 'values' => ['card']]]]))->toBe(['Beta Card'])
        ->and($names(['sortModel' => [['colId' => 'name', 'sort' => 'desc']]]))->toBe(['Gamma Cash', 'Beta Card', 'Alfa Bank']);

    $this->postJson('/api/tables/financial-accounts/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'card_number', 'sort' => 'asc']],
    ])->assertUnprocessable()->assertJsonValidationErrors('sortModel.0.colId');
});

it('AC-016: the hidden company column is filterable by company name', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny']));
    $company = Company::factory()->create(['denomination' => 'Acme Spa']);
    FinancialAccount::factory()->cash()->create(['name' => 'Tied', 'company_id' => $company->id]);
    FinancialAccount::factory()->cash()->create(['name' => 'Free']);

    $rows = $this->postJson('/api/tables/financial-accounts/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['company' => ['filterType' => 'set', 'values' => ['Acme Spa']]],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('name')->all())->toBe(['Tied'])
        ->and($rows[0]['company']['name'])->toBe('Acme Spa');
});

it('rows carry per-row actions according to the permissions and never any card data', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny', 'view', 'delete']));
    FinancialAccount::factory()->card()->create(['name' => 'Beta Card']);

    $row = $this->postJson('/api/tables/financial-accounts/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items.0');

    expect($row['actions'])->toEqualCanonicalizing(['view', 'delete'])
        ->and(array_keys($row))->not->toContain('card_number', 'card_last_four', 'card_number_masked');
});

it('bulk-delete applies the same guard as the single endpoint (D-10)', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny', 'delete']));
    $bank = FinancialAccount::factory()->bankAccount()->create();
    FinancialAccount::factory()->card()->create(['linked_account_id' => $bank->id]);
    $free = FinancialAccount::factory()->cash()->create();

    $this->postJson('/api/tables/financial-accounts/bulk-delete', ['ids' => [$bank->id, $free->id]])->assertOk();

    $this->assertDatabaseHas('financial_accounts', ['id' => $bank->id]);
    $this->assertDatabaseMissing('financial_accounts', ['id' => $free->id]);
});

it('AC-017: the export carries the visible columns and never a card number', function () {
    Storage::fake('local');
    Sanctum::actingAs(financialAccountUserWith(['viewAny', 'export']));
    FinancialAccount::factory()->card()->create(['name' => 'Beta Card']);
    FinancialAccount::factory()->bankAccount()->create(['name' => 'Alfa Bank']);

    $response = $this->postJson('/api/exports/financial-accounts', [
        'format' => 'csv',
        'columns' => [
            ['colId' => 'name', 'header' => 'Name'],
            ['colId' => 'iban', 'header' => 'IBAN'],
            ['colId' => 'type', 'header' => 'Type'],
        ],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();
    $csv = Storage::disk('local')->get($run->file_path);

    expect($run->row_count)->toBe(2)
        ->and($csv)->toContain('Alfa Bank')->toContain('Beta Card')
        ->and($csv)->not->toContain('4111')->not->toContain('1111111111');
});

it('AC-017: the export is 403 without financial-accounts.export', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny']));

    $this->postJson('/api/exports/financial-accounts', ['format' => 'csv', 'columns' => [['colId' => 'name', 'header' => 'Name']]])
        ->assertForbidden();
});
