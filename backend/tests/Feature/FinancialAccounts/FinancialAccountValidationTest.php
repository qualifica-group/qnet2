<?php

use App\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(financialAccountUserWith(['create', 'update'])));

it('AC-002: a bad IBAN checksum or garbage is a 422 on iban and creates nothing', function (string $iban) {
    $this->postJson('/api/financial-accounts', bankAccountPayload(['iban' => $iban]))
        ->assertUnprocessable()->assertJsonValidationErrors('iban');

    expect(FinancialAccount::count())->toBe(0);
})->with([
    'garbage' => 'xyz',
    'altered digit' => 'IT60X0542811101000000123457',
]);

it('AC-004: a credit card without linked_account_id is a 422; the same as prepaid is created', function () {
    $this->postJson('/api/financial-accounts', cardPayload(['card_type' => 'credit']))
        ->assertUnprocessable()->assertJsonValidationErrors('linked_account_id');

    $this->postJson('/api/financial-accounts', cardPayload(['card_type' => 'prepaid']))->assertCreated();
});

it('AC-004: a credit card with a bank account link is created and echoes linked_account', function () {
    $bank = FinancialAccount::factory()->bankAccount()->create();

    $this->postJson('/api/financial-accounts', cardPayload(['card_type' => 'credit', 'linked_account_id' => $bank->id]))
        ->assertCreated()
        ->assertJsonPath('data.linked_account.id', $bank->id)
        ->assertJsonPath('data.linked_account.iban', $bank->iban);
});

it('AC-004: a partial update turning a card into credit without a link is a 422', function () {
    $card = FinancialAccount::factory()->card()->create();

    $this->patchJson("/api/financial-accounts/{$card->id}", ['card_type' => 'credit'])
        ->assertUnprocessable()->assertJsonValidationErrors('linked_account_id');
});

it('AC-005: linked_account_id pointing to a card or a cash box is a 422', function (string $state) {
    $target = FinancialAccount::factory()->{$state}()->create();

    $this->postJson('/api/financial-accounts', cardPayload(['card_type' => 'credit', 'linked_account_id' => $target->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('linked_account_id');
})->with(['card', 'cash']);

it('AC-006: invalid card number or expiry is a 422 on the related field', function (array $override, string $field) {
    $this->postJson('/api/financial-accounts', cardPayload($override))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'not Luhn' => [['card_number' => '4111111111111112'], 'card_number'],
    'too short' => [['card_number' => '41111111111'], 'card_number'],
    'too long' => [['card_number' => '41111111111111111115'], 'card_number'],
    'expiry month 13' => [['card_expiry' => '13/2030'], 'card_expiry'],
    'expiry wrong format' => [['card_expiry' => '2030-12'], 'card_expiry'],
    'expiry short year' => [['card_expiry' => '12/30'], 'card_expiry'],
]);

it('AC-006: card_number is required on create', function () {
    $payload = cardPayload();
    unset($payload['card_number']);

    $this->postJson('/api/financial-accounts', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('card_number');
});

it('AC-009: fields of another type are a 422', function (string $builder, array $extra, array $fields) {
    $this->postJson('/api/financial-accounts', $builder($extra))
        ->assertUnprocessable()->assertJsonValidationErrors($fields);
})->with([
    'cash with iban' => ['cashPayload', ['iban' => 'IT60X0542811101000000123456'], ['iban']],
    'bank with card_number' => ['bankAccountPayload', ['card_number' => '4111111111111111'], ['card_number']],
    'card with iban and address' => ['cardPayload', ['iban' => 'IT60X0542811101000000123456', 'address_line' => 'Via Roma'], ['iban', 'address_line']],
    'cash with card fields' => ['cashPayload', ['card_type' => 'credit', 'card_holder' => 'X'], ['card_type', 'card_holder']],
]);

it('AC-009: update rejects fields of another type too', function () {
    $cash = FinancialAccount::factory()->cash()->create();

    $this->patchJson("/api/financial-accounts/{$cash->id}", ['iban' => 'IT60X0542811101000000123456'])
        ->assertUnprocessable()->assertJsonValidationErrors('iban');
});

it('requires the type and rejects an unknown one', function () {
    $this->postJson('/api/financial-accounts', ['name' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');

    $this->postJson('/api/financial-accounts', ['type' => 'wallet', 'name' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
});

it('requires name, iban and account_number for a bank account', function () {
    $this->postJson('/api/financial-accounts', ['type' => 'bank_account'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'iban', 'account_number']);
});

it('rejects an unknown company or geo id', function () {
    $this->postJson('/api/financial-accounts', cashPayload(['company_id' => 999999, 'country_id' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'country_id']);
});
