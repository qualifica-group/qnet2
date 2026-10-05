<?php

use App\Models\Company;
use App\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 / AC-003 — bank account create, IBAN normalization and uniqueness
// ---------------------------------------------------------------------------

it('AC-001: creates a bank account with the IBAN normalized and returns the Resource', function () {
    Sanctum::actingAs(financialAccountUserWith(['create']));
    $company = Company::factory()->create();
    $iban = bankAccountPayload()['iban'];

    $this->postJson('/api/financial-accounts', bankAccountPayload([
        'iban' => strtolower(chunk_split($iban, 4, ' ')),
        'company_id' => $company->id,
    ]))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Created')
        ->assertJsonPath('data.type', 'bank_account')
        ->assertJsonPath('data.iban', $iban)
        ->assertJsonPath('data.company.id', $company->id)
        ->assertJsonPath('data.card_number_masked', null)
        ->assertJsonStructure(['permissions', 'data' => [
            'id', 'type', 'name', 'company', 'iban', 'account_number', 'address_line', 'postal_code',
            'country', 'state', 'province', 'city', 'card_type', 'card_circuit', 'linked_account',
            'card_holder', 'card_number_masked', 'card_expiry', 'notes', 'created_at', 'updated_at',
        ]]);

    $this->assertDatabaseHas('financial_accounts', ['iban' => $iban, 'name' => 'Banca Test']);
});

it('AC-003: rejects an IBAN already used, written with spaces/lowercase, on create and update', function () {
    Sanctum::actingAs(financialAccountUserWith(['create', 'update']));
    $existing = FinancialAccount::factory()->bankAccount()->create();
    $other = FinancialAccount::factory()->bankAccount()->create();
    $spelled = strtolower(chunk_split($existing->iban, 4, ' '));

    $this->postJson('/api/financial-accounts', bankAccountPayload(['iban' => $spelled]))
        ->assertUnprocessable()->assertJsonValidationErrors('iban');

    $this->putJson("/api/financial-accounts/{$other->id}", ['iban' => $spelled])
        ->assertUnprocessable()->assertJsonValidationErrors('iban');

    // Resubmitting its own IBAN is not a conflict.
    $this->putJson("/api/financial-accounts/{$other->id}", ['iban' => $other->iban])->assertOk();
});

it('creates a cash box with a flat address', function () {
    Sanctum::actingAs(financialAccountUserWith(['create']));

    $this->postJson('/api/financial-accounts', cashPayload(['address_line' => 'Via Roma 1', 'postal_code' => '80100']))
        ->assertCreated()
        ->assertJsonPath('data.type', 'cash')
        ->assertJsonPath('data.address_line', 'Via Roma 1')
        ->assertJsonPath('data.iban', null);
});

// ---------------------------------------------------------------------------
// AC-007 / AC-015 — card number is encrypted, masked and never logged
// ---------------------------------------------------------------------------

it('AC-007: stores the card number encrypted and only ever exposes the masked form', function () {
    Sanctum::actingAs(financialAccountUserWith(['create', 'update', 'view', 'viewAny']));

    $id = $this->postJson('/api/financial-accounts', cardPayload())
        ->assertCreated()
        ->assertJsonPath('data.card_number_masked', '**** 1111')
        ->assertJsonMissingPath('data.card_number')
        ->json('data.id');

    $raw = DB::table('financial_accounts')->where('id', $id)->first();
    expect($raw->card_number)->not->toBeNull()
        ->and($raw->card_number)->not->toContain('4111')
        ->and($raw->card_last_four)->toBe('1111');

    $show = $this->getJson("/api/financial-accounts/{$id}")->assertOk();
    expect($show->getContent())->not->toContain('4111111111111111');

    $update = $this->putJson("/api/financial-accounts/{$id}", ['notes' => 'x'])->assertOk();
    expect($update->getContent())->not->toContain('4111111111111111');

    $rows = $this->postJson('/api/tables/financial-accounts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    expect($rows->getContent())->not->toContain('4111111111111111');
});

it('AC-015: the activity log of a card create/update never contains card_number', function () {
    Sanctum::actingAs(financialAccountUserWith(['create', 'update']));

    $id = $this->postJson('/api/financial-accounts', cardPayload())->assertCreated()->json('data.id');
    $this->putJson("/api/financial-accounts/{$id}", ['card_number' => '5555555555554444', 'card_holder' => 'Luigi Verdi'])->assertOk();

    $logs = DB::table('activity_log')->where('subject_id', $id)->get();
    expect($logs)->not->toBeEmpty();

    foreach ($logs as $log) {
        expect($log->properties)->not->toContain('card_number')
            ->and($log->properties)->not->toContain('4111')
            ->and($log->properties)->not->toContain('5555');
    }
});

// ---------------------------------------------------------------------------
// AC-008 — CVV / PIN never persisted
// ---------------------------------------------------------------------------

it('AC-008: rejects cvv/card_ccv/card_password in the payload', function (string $field) {
    Sanctum::actingAs(financialAccountUserWith(['create']));

    $this->postJson('/api/financial-accounts', cardPayload([$field => '123']))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(FinancialAccount::count())->toBe(0);
})->with(['cvv', 'card_ccv', 'card_password']);

it('AC-008: financial_accounts has no cvv/password column', function () {
    foreach (['cvv', 'card_ccv', 'card_password', 'password', 'pin'] as $column) {
        expect(Schema::hasColumn('financial_accounts', $column))->toBeFalse();
    }
});

// ---------------------------------------------------------------------------
// AC-010 / AC-011 — update semantics
// ---------------------------------------------------------------------------

it('AC-010: a different type on update is a 422 and the record is unchanged', function () {
    Sanctum::actingAs(financialAccountUserWith(['update']));
    $account = FinancialAccount::factory()->bankAccount()->create(['name' => 'Originale']);

    $this->putJson("/api/financial-accounts/{$account->id}", ['type' => 'cash', 'name' => 'Cambiato'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');

    expect($account->fresh()->name)->toBe('Originale');

    // The same type is accepted.
    $this->putJson("/api/financial-accounts/{$account->id}", ['type' => 'bank_account', 'name' => 'Nuovo'])->assertOk();
});

it('AC-011: update without card_number keeps it; a new valid one updates card_last_four', function () {
    Sanctum::actingAs(financialAccountUserWith(['update']));
    $card = FinancialAccount::factory()->card()->create();

    $this->putJson("/api/financial-accounts/{$card->id}", ['card_holder' => 'Luigi Verdi'])
        ->assertOk()->assertJsonPath('data.card_number_masked', '**** 1111');

    expect($card->fresh()->card_number)->toBe('4111111111111111');

    $this->patchJson("/api/financial-accounts/{$card->id}", ['card_number' => '5555 5555 5555 4444'])
        ->assertOk()->assertJsonPath('data.card_number_masked', '**** 4444');

    expect($card->fresh()->card_number)->toBe('5555555555554444')
        ->and($card->fresh()->card_last_four)->toBe('4444');
});

// ---------------------------------------------------------------------------
// AC-012 — delete guard
// ---------------------------------------------------------------------------

it('AC-012: a bank account with linked cards answers 409; without cards it is deleted (204)', function () {
    Sanctum::actingAs(financialAccountUserWith(['delete']));
    $bank = FinancialAccount::factory()->bankAccount()->create();
    FinancialAccount::factory()->card()->create(['linked_account_id' => $bank->id]);
    $free = FinancialAccount::factory()->bankAccount()->create();

    $this->deleteJson("/api/financial-accounts/{$bank->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This account has linked cards and cannot be deleted.');
    $this->assertDatabaseHas('financial_accounts', ['id' => $bank->id]);

    $this->deleteJson("/api/financial-accounts/{$free->id}")->assertNoContent();
    $this->assertDatabaseMissing('financial_accounts', ['id' => $free->id]);
});

it('deleting a card is allowed and a missing account is 404', function () {
    Sanctum::actingAs(financialAccountUserWith(['delete', 'view']));
    $card = FinancialAccount::factory()->card()->create();

    $this->deleteJson("/api/financial-accounts/{$card->id}")->assertNoContent();
    $this->getJson('/api/financial-accounts/999999')->assertNotFound();
});
