<?php

use App\Models\Contract;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0315: from a closed-won Offerta the Contract born from it opens in a
 * modal — the `contract` row action of the `quotes` grid and the `contract_id`
 * of the detail, both gated by `contracts.view` (ContractPolicy::view).
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions  full permission names
 */
function quoteContractShortcutUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission));
    }

    // Spec 0316: not a visibility test, so the actor sees every quote.
    $user->givePermissionTo(Permission::findOrCreate('quotes.viewAll', 'web'));

    return $user;
}

/**
 * @return array<int, array<string, mixed>>
 */
function quoteContractShortcutRows(): array
{
    return test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items');
}

it('AC-001: declares the contract action after notes only for contracts.view', function () {
    // Both actors up front: once Sanctum acts, findOrCreate would use its guard.
    $withoutContracts = quoteContractShortcutUser(['quotes.viewAny', 'quotes.view', 'request-management.view']);
    $withContracts = quoteContractShortcutUser(['quotes.viewAny', 'quotes.view', 'request-management.view', 'contracts.view']);

    Sanctum::actingAs($withoutContracts);
    $keys = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.actions'))->pluck('key');
    expect($keys)->not->toContain('contract');

    Sanctum::actingAs($withContracts);
    $actions = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.actions'));

    expect($actions->pluck('key')->all())->toBe(['view', 'generate_document', 'notes', 'contract'])
        ->and($actions->firstWhere('key', 'contract'))->toMatchArray([
            'label' => 'actions.openContract',
            'icon' => 'file-signature',
            'type' => 'action',
            'confirm' => false,
        ]);
});

it('AC-002: only an Offerta with a contract carries contract_id and the contract action', function () {
    $contract = Contract::factory()->create();
    $plain = Quote::factory()->create();
    Sanctum::actingAs(quoteContractShortcutUser(['quotes.viewAny', 'quotes.view', 'contracts.view', 'contracts.viewAll']));

    $rows = collect(quoteContractShortcutRows())->keyBy('id');

    expect($rows[$contract->quote_id]['contract_id'])->toBe($contract->id)
        ->and($rows[$contract->quote_id]['actions'])->toContain('contract')
        ->and($rows[$plain->id]['contract_id'])->toBeNull()
        ->and($rows[$plain->id]['actions'])->not->toContain('contract');
});

it('AC-002: without contracts.view the row offers no contract action', function () {
    $contract = Contract::factory()->create();
    Sanctum::actingAs(quoteContractShortcutUser(['quotes.viewAny', 'quotes.view']));

    $row = collect(quoteContractShortcutRows())->firstWhere('id', $contract->quote_id);

    expect($row['actions'])->not->toContain('contract');
});

it('AC-003: the detail exposes contract_id, null when the Offerta has no contract', function () {
    $contract = Contract::factory()->create();
    $plain = Quote::factory()->create();
    Sanctum::actingAs(quoteContractShortcutUser(['quotes.view']));

    $this->getJson("/api/quotes/{$contract->quote_id}")
        ->assertOk()
        ->assertJsonPath('data.contract_id', $contract->id);

    $this->getJson("/api/quotes/{$plain->id}")
        ->assertOk()
        ->assertJsonPath('data.contract_id', null);
});
