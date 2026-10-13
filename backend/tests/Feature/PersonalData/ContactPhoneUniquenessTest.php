<?php

use App\Enums\ContactTypeEnum;
use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-10-08: POST/PUT /api/contacts — the endpoint the
// anagrafica, referente and profile details add contacts through — applies the
// same namespace-wide phone uniqueness as the forms. Cards outside the
// namespace (company sites) are never checked; a number the contact already
// holds is a no-op, not a new duplicate.

uses(RefreshDatabase::class);

const CONTACT_TAKEN_PHONE = '3339876543';

function contactPhoneActor(): User
{
    foreach (['create', 'update'] as $ability) {
        Permission::findOrCreate("contacts.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['contacts.create', 'contacts.update']);

    return $actor;
}

function registryCardForContacts(): PersonalData
{
    return PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')->create();
}

function phoneOn(PersonalData $card, string $value): Contact
{
    return Contact::factory()->create([
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Phone->value,
        'value' => $value,
    ]);
}

function referentCardHoldingTakenPhone(): void
{
    phoneOn(PersonalData::factory()->individual()->for(Referent::factory()->create(), 'personable')->create(), CONTACT_TAKEN_PHONE);
}

it('create: 422 when the phone already belongs to a referente', function () {
    $card = registryCardForContacts();
    referentCardHoldingTakenPhone();
    Sanctum::actingAs(contactPhoneActor());

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Phone->value,
        'value' => '333 987 6543',
    ])->assertStatus(422)->assertJsonValidationErrors(['value' => 'already assigned']);

    expect($card->contacts()->count())->toBe(0);
});

it('create: 201 for a free phone', function () {
    $card = registryCardForContacts();
    Sanctum::actingAs(contactPhoneActor());

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Phone->value,
        'value' => CONTACT_TAKEN_PHONE,
    ])->assertCreated();
});

it('create: 201 on a company-site card, outside the namespace', function () {
    $siteCard = PersonalData::factory()->company()->for(CompanySite::factory()->create(), 'personable')->create();
    referentCardHoldingTakenPhone();
    Sanctum::actingAs(contactPhoneActor());

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $siteCard->id,
        'type' => ContactTypeEnum::Phone->value,
        'value' => CONTACT_TAKEN_PHONE,
    ])->assertCreated();
});

it('update: 422 when the new phone already belongs to another card', function () {
    $contact = phoneOn(registryCardForContacts(), '0811234567');
    referentCardHoldingTakenPhone();
    Sanctum::actingAs(contactPhoneActor());

    $this->putJson("/api/contacts/{$contact->id}", [
        'type' => ContactTypeEnum::Phone->value,
        'value' => CONTACT_TAKEN_PHONE,
    ])->assertStatus(422)->assertJsonValidationErrors(['value' => 'already assigned']);

    expect($contact->fresh()->value)->toBe('0811234567');
});

it('update: keeping a legacy duplicate number is not refused', function () {
    $contact = phoneOn(registryCardForContacts(), CONTACT_TAKEN_PHONE);
    referentCardHoldingTakenPhone();
    Sanctum::actingAs(contactPhoneActor());

    $this->putJson("/api/contacts/{$contact->id}", [
        'type' => ContactTypeEnum::Phone->value,
        'value' => CONTACT_TAKEN_PHONE,
        'label' => 'Centralino',
    ])->assertOk();

    expect($contact->fresh()->label)->toBe('Centralino');
});
