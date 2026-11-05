<?php

use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0355 — balances: AC-016.

function balancesActor(bool $view = true): User
{
    Permission::findOrCreate('treasury-movements.view');
    $user = User::factory()->create();

    return $view ? $user->givePermissionTo('treasury-movements.view') : $user;
}

it('AC-016: with company_id only that company accounts, without it all with total = sum', function () {
    Sanctum::actingAs(balancesActor());
    $company = Company::factory()->create();
    $first = FinancialAccount::factory()->create(['company_id' => $company->id, 'name' => 'A', 'opening_balance' => '100.00']);
    $second = FinancialAccount::factory()->create(['name' => 'B', 'opening_balance' => '50.50']);
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $first->id, 'amount' => '30.00', 'movement_date' => '2026-02-10']);
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $first->id, 'amount' => '1.00', 'movement_date' => '2026-06-10']);

    $scoped = $this->getJson("/api/account-statements/balances?company_id={$company->id}&date=2026-03-01")->assertOk()->json('data');
    expect($scoped['date'])->toBe('2026-03-01')
        ->and($scoped['accounts'])->toHaveCount(1)
        ->and($scoped['accounts'][0])->toMatchArray(['id' => $first->id, 'name' => 'A', 'type' => $first->type->value, 'balance' => '70.00'])
        ->and($scoped['accounts'][0]['company'])->toBe(['id' => $company->id, 'name' => $company->denomination])
        ->and($scoped['total'])->toBe('70.00');

    $all = $this->getJson('/api/account-statements/balances?date=2026-12-31')->json('data');
    expect($all['accounts'])->toHaveCount(2)
        ->and($all['accounts'][1]['id'])->toBe($second->id)
        ->and($all['total'])->toBe('119.50');
});

it('AC-016: 401, 403 without view, 422 unknown company', function () {
    $this->getJson('/api/account-statements/balances')->assertUnauthorized();

    Sanctum::actingAs(balancesActor(false));
    $this->getJson('/api/account-statements/balances')->assertForbidden();

    Sanctum::actingAs(balancesActor());
    $this->getJson('/api/account-statements/balances?company_id=999999')->assertStatus(422);
});
