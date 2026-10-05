<?php

use App\Models\FinancialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-013 — 403 without the matching permission, 401 without authentication
// ---------------------------------------------------------------------------

it('AC-013: 403 without the matching permission on every endpoint, no row written', function () {
    Sanctum::actingAs(financialAccountUserWith([]));
    $card = FinancialAccount::factory()->card()->create();
    $before = FinancialAccount::count();

    $this->getJson("/api/financial-accounts/{$card->id}")->assertForbidden();
    $this->postJson('/api/financial-accounts', cashPayload())->assertForbidden();
    $this->putJson("/api/financial-accounts/{$card->id}", ['card_holder' => 'X'])->assertForbidden();
    $this->deleteJson("/api/financial-accounts/{$card->id}")->assertForbidden();
    $this->getJson("/api/financial-accounts/{$card->id}/card-number")->assertForbidden();
    $this->getJson('/api/tables/financial-accounts/columns')->assertForbidden();
    $this->postJson('/api/tables/financial-accounts/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();

    expect(FinancialAccount::count())->toBe($before)
        ->and($card->fresh()->card_holder)->toBe($card->card_holder);
});

it('AC-013: each ability grants only its own endpoint', function () {
    $card = FinancialAccount::factory()->card()->create();

    Sanctum::actingAs(financialAccountUserWith(['view']));
    $this->getJson("/api/financial-accounts/{$card->id}")->assertOk();
    $this->getJson("/api/financial-accounts/{$card->id}/card-number")->assertForbidden();
    $this->deleteJson("/api/financial-accounts/{$card->id}")->assertForbidden();

    Sanctum::actingAs(financialAccountUserWith(['revealCardNumber']));
    $this->getJson("/api/financial-accounts/{$card->id}")->assertForbidden();
    $this->getJson("/api/financial-accounts/{$card->id}/card-number")->assertOk();
});

it('AC-013: 401 without authentication', function () {
    $card = FinancialAccount::factory()->card()->create();

    $this->getJson("/api/financial-accounts/{$card->id}")->assertUnauthorized();
    $this->postJson('/api/financial-accounts', cashPayload())->assertUnauthorized();
    $this->putJson("/api/financial-accounts/{$card->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/financial-accounts/{$card->id}")->assertUnauthorized();
    $this->getJson("/api/financial-accounts/{$card->id}/card-number")->assertUnauthorized();
    $this->getJson('/api/financial-accounts/for-select')->assertUnauthorized();
    $this->postJson('/api/tables/financial-accounts/rows', ['startRow' => 0, 'endRow' => 25])->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// AC-014 — reveal: audited, card-only
// ---------------------------------------------------------------------------

it('AC-014: reveal returns the clear number and writes an activity-log entry with the actor', function () {
    $actor = financialAccountUserWith(['revealCardNumber']);
    Sanctum::actingAs($actor);
    $card = FinancialAccount::factory()->card()->create();

    $this->getJson("/api/financial-accounts/{$card->id}/card-number")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.card_number', '4111111111111111');

    $entry = DB::table('activity_log')->where('subject_id', $card->id)->where('event', 'card_number_revealed')->first();

    expect($entry)->not->toBeNull()
        ->and((int) $entry->causer_id)->toBe($actor->id)
        ->and($entry->properties)->not->toContain('4111');
});

it('AC-014: reveal on a non-card account is a 404 and logs nothing', function (string $state) {
    Sanctum::actingAs(financialAccountUserWith(['revealCardNumber']));
    $account = FinancialAccount::factory()->{$state}()->create();

    $this->getJson("/api/financial-accounts/{$account->id}/card-number")->assertNotFound();

    expect(DB::table('activity_log')->where('event', 'card_number_revealed')->count())->toBe(0);
})->with(['bankAccount', 'cash']);

it('AC-014: reveal on a card without a stored number is a 404', function () {
    Sanctum::actingAs(financialAccountUserWith(['revealCardNumber']));
    $card = FinancialAccount::factory()->card()->create(['card_number' => null, 'card_last_four' => null]);

    $this->getJson("/api/financial-accounts/{$card->id}/card-number")->assertNotFound();
});

it('the detail permissions block offers reveal_card_number only on a card, to its holders', function () {
    Sanctum::actingAs(financialAccountUserWith(['view', 'revealCardNumber']));
    $card = FinancialAccount::factory()->card()->create();
    $bank = FinancialAccount::factory()->bankAccount()->create();

    $this->getJson("/api/financial-accounts/{$card->id}")->assertOk()->assertJsonPath('permissions.actions.reveal_card_number', true);
    $this->getJson("/api/financial-accounts/{$bank->id}")->assertOk()->assertJsonPath('permissions.actions.reveal_card_number', false);

    Sanctum::actingAs(financialAccountUserWith(['view']));
    $this->getJson("/api/financial-accounts/{$card->id}")->assertOk()->assertJsonPath('permissions.actions.reveal_card_number', false);
});
