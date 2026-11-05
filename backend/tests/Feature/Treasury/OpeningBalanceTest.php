<?php

use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0360 — opening balance: AC-001, AC-002.

function openingActor(array $permissions): User
{
    $user = User::factory()->create();
    foreach (['financial-accounts.manageOpeningBalance', 'financial-accounts.view', 'financial-accounts.update', 'treasury-movements.view'] as $name) {
        Permission::findOrCreate($name);
    }
    foreach ($permissions as $name) {
        $user->givePermissionTo($name);
    }

    return $user;
}

it('AC-001: no opening balance gives 2026-01-01 and 0.00; the PATCH sets it and audits before/after', function () {
    Sanctum::actingAs(openingActor(['financial-accounts.manageOpeningBalance', 'treasury-movements.view']));
    $account = FinancialAccount::factory()->create();

    $statement = $this->getJson("/api/financial-accounts/{$account->id}/statement?date_from=2026-02-01&date_to=2026-02-28")->assertOk();
    expect($statement->json('data.opening'))->toBe(['date' => '2026-01-01', 'balance' => '0.00']);

    $response = $this->patchJson("/api/financial-accounts/{$account->id}/opening-balance", ['opening_balance' => 1000, 'opening_balance_date' => '2026-01-01'])
        ->assertOk()
        ->assertJsonPath('data.opening_balance', '1000.00')
        ->assertJsonPath('data.opening_balance_date', '2026-01-01');
    expect($response->json('data.current_balance'))->toBe('1000.00');

    expect($this->getJson("/api/financial-accounts/{$account->id}/statement?date_from=2026-02-01&date_to=2026-02-28")->json('data.period_opening_balance'))->toBe('1000.00');

    $log = DB::table('activity_log')->where('subject_id', $account->id)->where('event', 'updated')->latest('id')->first();
    $properties = json_decode($log->properties, true);
    expect($properties['attributes']['opening_balance'])->toBe('1000.00')
        ->and($properties['old']['opening_balance'])->toBe('0.00');
});

it('AC-001: the resource exposes the effective default date and hides current_balance without view', function () {
    Sanctum::actingAs(openingActor(['financial-accounts.view']));
    $account = FinancialAccount::factory()->create();

    $this->getJson("/api/financial-accounts/{$account->id}")->assertOk()
        ->assertJsonPath('data.opening_balance', '0.00')
        ->assertJsonPath('data.opening_balance_date', '2026-01-01')
        ->assertJsonPath('data.current_balance', null);
});

it('AC-002: 403 without manageOpeningBalance and nothing changes', function () {
    Sanctum::actingAs(openingActor(['financial-accounts.update']));
    $account = FinancialAccount::factory()->create(['opening_balance' => '5.00']);

    $this->patchJson("/api/financial-accounts/{$account->id}/opening-balance", ['opening_balance' => 99, 'opening_balance_date' => '2026-01-01'])->assertForbidden();

    expect($account->fresh()->opening_balance)->toBe('5.00');
});

it('AC-002: the normal update rejects the opening balance (dedicated endpoint only)', function () {
    Sanctum::actingAs(openingActor(['financial-accounts.update', 'financial-accounts.manageOpeningBalance']));
    $account = FinancialAccount::factory()->create(['opening_balance' => '5.00']);

    $this->patchJson("/api/financial-accounts/{$account->id}", ['name' => 'Renamed', 'opening_balance' => 99])->assertStatus(422);

    expect($account->fresh()->opening_balance)->toBe('5.00')->and($account->fresh()->name)->not->toBe('Renamed');
    $this->patchJson("/api/financial-accounts/{$account->id}", ['name' => 'Renamed'])->assertOk();
});

it('AC-002: an opening date after a manual movement is 422; invalid payloads are 422', function () {
    Sanctum::actingAs(openingActor(['financial-accounts.manageOpeningBalance']));
    $account = FinancialAccount::factory()->create();
    TreasuryMovement::factory()->create(['financial_account_id' => $account->id, 'movement_date' => '2026-02-10']);

    $this->patchJson("/api/financial-accounts/{$account->id}/opening-balance", ['opening_balance' => 10, 'opening_balance_date' => '2026-03-01'])->assertStatus(422);
    $this->patchJson("/api/financial-accounts/{$account->id}/opening-balance", ['opening_balance' => 10, 'opening_balance_date' => '2026-02-10'])->assertOk();
    $this->patchJson("/api/financial-accounts/{$account->id}/opening-balance", ['opening_balance' => 1.234, 'opening_balance_date' => '2026-02-10'])->assertStatus(422);
    $this->patchJson("/api/financial-accounts/{$account->id}/opening-balance", ['opening_balance_date' => '2026-02-10'])->assertStatus(422);
    $this->patchJson('/api/financial-accounts/999999/opening-balance', ['opening_balance' => 1, 'opening_balance_date' => '2026-02-10'])->assertNotFound();
});
