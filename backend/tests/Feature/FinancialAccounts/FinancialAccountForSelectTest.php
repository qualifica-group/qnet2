<?php

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-018: type=bank_account returns only bank accounts, subtitle = iban, search on the name', function () {
    Sanctum::actingAs(User::factory()->create());
    $alfa = FinancialAccount::factory()->bankAccount()->create(['name' => 'Alfa Bank']);
    FinancialAccount::factory()->bankAccount()->create(['name' => 'Beta Bank']);
    FinancialAccount::factory()->card()->create(['name' => 'Alfa Card']);
    FinancialAccount::factory()->cash()->create(['name' => 'Alfa Cash']);

    $all = $this->getJson('/api/financial-accounts/for-select?type=bank_account')
        ->assertOk()
        ->assertJsonStructure(['items', 'export_link', 'pagination']);

    expect(collect($all->json('items'))->pluck('label')->all())->toBe(['Alfa Bank', 'Beta Bank'])
        ->and($all->json('items.0'))->toMatchArray([
            'id' => $alfa->id,
            'label' => 'Alfa Bank',
            'subtitle' => $alfa->iban,
            'meta' => ['type' => 'bank_account'],
        ]);

    $searched = $this->getJson('/api/financial-accounts/for-select?type=bank_account&search=alfa')->assertOk();
    expect(collect($searched->json('items'))->pluck('label')->all())->toBe(['Alfa Bank']);
});

it('without type it lists every account; cash and cards have no subtitle', function () {
    Sanctum::actingAs(User::factory()->create());
    FinancialAccount::factory()->cash()->create(['name' => 'Cassa']);
    FinancialAccount::factory()->card()->create(['name' => 'Carta']);

    $items = collect($this->getJson('/api/financial-accounts/for-select')->assertOk()->json('items'));

    expect($items->pluck('label')->sort()->values()->all())->toBe(['Carta', 'Cassa'])
        ->and($items->every(fn (array $item): bool => ! array_key_exists('subtitle', $item)))->toBeTrue();
});

it('rejects an unknown type and hydrates the requested ids for edit mode', function () {
    Sanctum::actingAs(User::factory()->create());
    $bank = FinancialAccount::factory()->bankAccount()->create(['name' => 'Zeta Bank']);

    $this->getJson('/api/financial-accounts/for-select?type=wallet')->assertUnprocessable();

    $items = $this->getJson("/api/financial-accounts/for-select?type=bank_account&search=nomatch&ids[]={$bank->id}")
        ->assertOk()->json('items');

    expect(collect($items)->pluck('id')->all())->toBe([$bank->id]);
});
