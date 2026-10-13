<?php

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-10-08: the EXISTING client a request edits obeys the
// identity namespace too. Both write channels — the work panel
// (PATCH /api/request-management/{quote}) and the grid's inline cells
// (PATCH /api/tables/request-management/rows/{quote}) — refuse a codice
// fiscale, partita IVA or phone another user/anagrafica/referente already
// holds (RequestClientUniquenessGuard), while a value the client's own card
// already carries stays a no-op.

uses(RefreshDatabase::class);

const EXISTING_CLIENT_VAT = '09876543217';

const EXISTING_CLIENT_PHONE = '3331234567';

function existingClientEditor(): User
{
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.update']);

    return $actor;
}

function existingClientRequest(User $operator): Quote
{
    $registry = Registry::factory()->withPersonalData(fn ($card) => $card->company())->create();
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

    return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
}

function existingClientCard(Quote $quote): PersonalData
{
    return $quote->opportunity->registry->personalData;
}

function anotherCardHoldingPhone(string $phone): void
{
    $card = PersonalData::factory()->individual()->for(Referent::factory()->create(), 'personable')->create();
    Contact::factory()->create([
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'phone',
        'value' => $phone,
    ]);
}

/** @return array<string, mixed> */
function existingClientCompanyIdentity(array $overrides = []): array
{
    return array_merge(['type' => 'company', 'company_name' => 'Cliente Srl'], $overrides);
}

// ---------------------------------------------------------------------------
// Work panel
// ---------------------------------------------------------------------------

it('panel: refuses a partita IVA another anagrafica already holds (422)', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['vat_number' => EXISTING_CLIENT_VAT]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => existingClientCompanyIdentity(['vat_number' => EXISTING_CLIENT_VAT]),
    ])->assertStatus(422)->assertJsonValidationErrors(['client_identity.vat_number' => 'already assigned']);

    expect(existingClientCard($quote)->fresh()->vat_number)->not->toBe(EXISTING_CLIENT_VAT);
});

it('panel: refuses a codice fiscale equal to another card partita IVA (cross-column, 422)', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['tax_code' => null, 'vat_number' => EXISTING_CLIENT_VAT]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => existingClientCompanyIdentity(['tax_code' => EXISTING_CLIENT_VAT]),
    ])->assertStatus(422)->assertJsonValidationErrors(['client_identity.tax_code' => 'already assigned']);
});

it('panel: keeping a fiscal identifier the card already holds is not a new duplicate', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    existingClientCard($quote)->update(['vat_number' => EXISTING_CLIENT_VAT]);
    // A legacy duplicate: another anagrafica carries the very same P.IVA.
    PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['vat_number' => EXISTING_CLIENT_VAT]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => existingClientCompanyIdentity(['company_name' => 'Rinominata Srl', 'vat_number' => EXISTING_CLIENT_VAT]),
    ])->assertOk();

    expect(existingClientCard($quote)->fresh()->company_name)->toBe('Rinominata Srl');
});

it('panel: refuses a new phone another card already holds (422 on its row)', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    anotherCardHoldingPhone(EXISTING_CLIENT_PHONE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_contacts' => [
            ['type' => 'email', 'value' => 'cliente@example.test'],
            ['type' => 'phone', 'value' => '333 123 4567'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['client_contacts.1.value' => 'already assigned']);

    expect(existingClientCard($quote)->contacts()->count())->toBe(0);
});

it('panel: a phone the card already holds does not block the save', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    $own = Contact::factory()->create([
        'contactable_type' => 'personal_data',
        'contactable_id' => existingClientCard($quote)->id,
        'type' => 'phone',
        'value' => EXISTING_CLIENT_PHONE,
    ]);
    anotherCardHoldingPhone(EXISTING_CLIENT_PHONE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_contacts' => [
            ['id' => $own->id, 'type' => 'phone', 'value' => EXISTING_CLIENT_PHONE],
            ['type' => 'email', 'value' => 'cliente@example.test'],
        ],
    ])->assertOk();
});

it('panel: a free partita IVA and phone are written', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => existingClientCompanyIdentity(['vat_number' => EXISTING_CLIENT_VAT]),
        'client_contacts' => [['type' => 'phone', 'value' => EXISTING_CLIENT_PHONE]],
    ])->assertOk();

    expect(existingClientCard($quote)->fresh()->vat_number)->toBe(EXISTING_CLIENT_VAT);
});

// ---------------------------------------------------------------------------
// Inline grid cells
// ---------------------------------------------------------------------------

it('inline: refuses a partita IVA another referente already holds (422)', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    PersonalData::factory()->company()->for(Referent::factory()->create(), 'personable')
        ->create(['vat_number' => EXISTING_CLIENT_VAT]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'vat_number',
        'value' => EXISTING_CLIENT_VAT,
    ])->assertStatus(422)->assertJsonValidationErrors(['client_vat_number' => 'already assigned']);

    expect(existingClientCard($quote)->fresh()->vat_number)->not->toBe(EXISTING_CLIENT_VAT);
});

it('inline: refuses a phone another card already holds (422)', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    anotherCardHoldingPhone(EXISTING_CLIENT_PHONE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'phone',
        'value' => EXISTING_CLIENT_PHONE,
    ])->assertStatus(422)->assertJsonValidationErrors(['client_phone' => 'already assigned']);

    expect(existingClientCard($quote)->contacts()->count())->toBe(0);
});

it('inline: a free codice fiscale is written', function () {
    $actor = existingClientEditor();
    $quote = existingClientRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'tax_code',
        'value' => EXISTING_CLIENT_VAT,
    ])->assertOk();

    expect(existingClientCard($quote)->fresh()->tax_code)->toBe(EXISTING_CLIENT_VAT);
});
