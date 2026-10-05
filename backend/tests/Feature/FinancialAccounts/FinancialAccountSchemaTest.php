<?php

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('AC-019: permissions:sync creates financial-accounts.* including revealCardNumber, never import', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'revealCardNumber'] as $ability) {
        expect(Permission::where('name', "financial-accounts.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'financial-accounts.import')->exists())->toBeFalse();
});

it('AC-019: the accounting section with the financial-accounts entry shows only with financial-accounts.view', function () {
    Permission::findOrCreate('financial-accounts.view');

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('accounting')->not->toContain('financial-accounts');

    $user = User::factory()->create();
    $user->givePermissionTo('financial-accounts.view');
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/navigation')->assertOk()->json('data');
    $keys = navigationNodeKeys($data);
    $node = collect($data)->firstWhere('key', 'accounting');

    expect($keys)->toContain('accounting')->toContain('financial-accounts')
        ->and($node['label'])->toBe('navigation.accounting')
        ->and($node['children'][0])->toMatchArray([
            'key' => 'financial-accounts',
            'label' => 'navigation.financialAccounts',
            'icon' => 'landmark',
            'route' => '/financial-accounts',
        ])
        ->and(array_column(config('navigation.items'), 'key'))->toContain('accounting', 'administration');

    $order = array_column(config('navigation.items'), 'key');
    expect(array_search('accounting', $order, true))->toBe(array_search('administration', $order, true) - 1);
});

it('AC-020: the migration creates the contract columns, the unique IBAN and rolls back cleanly', function () {
    expect(Schema::hasTable('financial_accounts'))->toBeTrue()
        ->and(Schema::hasColumns('financial_accounts', [
            'id', 'type', 'name', 'company_id', 'iban', 'account_number', 'address_line', 'postal_code',
            'country_id', 'state_id', 'province_id', 'city_id', 'card_type', 'card_circuit',
            'linked_account_id', 'card_holder', 'card_number', 'card_last_four', 'card_expiry', 'notes',
            'created_at', 'updated_at',
        ]))->toBeTrue();

    $ibanIndex = collect(Schema::getIndexes('financial_accounts'))->first(fn (array $index): bool => $index['columns'] === ['iban']);
    expect($ibanIndex['unique'])->toBeTrue();

    $foreignKeys = collect(Schema::getForeignKeys('financial_accounts'))->keyBy(fn (array $fk): string => $fk['columns'][0]);
    expect($foreignKeys->keys()->all())->toEqualCanonicalizing(['company_id', 'country_id', 'state_id', 'province_id', 'city_id', 'linked_account_id'])
        ->and($foreignKeys['company_id']['foreign_table'])->toBe('companies')
        ->and($foreignKeys['company_id']['on_delete'])->toBe('set null')
        ->and($foreignKeys['linked_account_id']['foreign_table'])->toBe('financial_accounts')
        ->and($foreignKeys['linked_account_id']['on_delete'])->toBe('restrict');
});

it('AC-020: the migration down() drops the table', function () {
    $migration = require database_path('migrations/2026_10_05_100000_create_financial_accounts_table.php');

    $migration->down();
    expect(Schema::hasTable('financial_accounts'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('financial_accounts'))->toBeTrue();
});

it('hides card_number from serialization and encrypts it at rest', function () {
    $card = FinancialAccount::factory()->card()->create();

    expect($card->toArray())->not->toHaveKey('card_number')
        ->and($card->getRawOriginal('card_number'))->not->toBe('4111111111111111')
        ->and($card->fresh()->card_number)->toBe('4111111111111111');
});

it('GET /api/meta/financial-accounts resolves in create mode with the field catalogue and action keys', function () {
    Sanctum::actingAs(financialAccountUserWith(['viewAny', 'create']));

    $response = $this->getJson('/api/meta/financial-accounts')->assertOk()->assertJsonPath('success', true);
    $keys = collect($response->json('data.fields'))->pluck('key')->all();

    expect($keys)->toContain('type', 'name', 'iban', 'card_number', 'linked_account_id', 'company_id')
        ->and($response->json('permissions.fields.type.editable'))->toBeTrue()
        ->and(array_keys($response->json('permissions.actions')))->toContain('delete', 'export', 'view_activity', 'reveal_card_number');
});

it('show/store/update permissions.actions always carry delete, export, view_activity and reveal_card_number', function () {
    Sanctum::actingAs(financialAccountUserWith(['view', 'create', 'update']));
    $account = FinancialAccount::factory()->bankAccount()->create();

    $actions = fn ($response) => array_keys($response->assertSuccessful()->json('permissions.actions'));

    expect($actions($this->getJson("/api/financial-accounts/{$account->id}")))->toContain('delete', 'export', 'view_activity', 'reveal_card_number')
        ->and($actions($this->postJson('/api/financial-accounts', cashPayload())))->toContain('delete', 'export', 'view_activity', 'reveal_card_number')
        ->and($actions($this->patchJson("/api/financial-accounts/{$account->id}", ['name' => 'N'])))->toContain('delete', 'export', 'view_activity', 'reveal_card_number');
});

it('a PATCH resending the full field set with the unchanged spaced/lowercase IBAN succeeds', function () {
    Sanctum::actingAs(financialAccountUserWith(['update']));
    $account = FinancialAccount::factory()->bankAccount()->create();

    $this->patchJson("/api/financial-accounts/{$account->id}", [
        'type' => 'bank_account',
        'name' => 'Renamed',
        'iban' => strtolower(chunk_split($account->iban, 4, ' ')),
        'account_number' => $account->account_number,
        'company_id' => null,
        'notes' => null,
    ])->assertOk()->assertJsonPath('data.iban', $account->iban)->assertJsonPath('data.name', 'Renamed');
});
