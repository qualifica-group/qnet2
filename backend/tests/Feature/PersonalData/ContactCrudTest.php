<?php

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// view — GET /api/contacts/{contact}
// ---------------------------------------------------------------------------

it('view: 200 re-exposes the hidden value', function () {
    $actor = registryCardActor(['view']);
    $contact = Contact::factory()->email()->for(registryCard(), 'contactable')->create(['value' => 'ada@example.com']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.value', 'ada@example.com')
        ->assertJsonPath('data.type', ContactTypeEnum::Email->value);
});

it('view: 403 without view on the owning record', function () {
    $actor = registryCardActor([]);
    $contact = Contact::factory()->for(registryCard(), 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/contacts/{$contact->id}")->assertForbidden();
});

it('view: 404 for a non-existent contact', function () {
    $actor = registryCardActor(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contacts/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/contacts
// ---------------------------------------------------------------------------

it('create: 201 attaches a contact to a personal-data owner', function () {
    $actor = registryCardActor(['update']);
    $card = registryCard();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Email->value,
        'value' => 'grace@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.value', 'grace@example.com')
        ->assertJsonPath('data.contactable_type', 'personal_data')
        ->assertJsonPath('data.contactable_id', $card->id);

    $this->assertDatabaseHas('contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'value' => 'grace@example.com',
    ]);
});

it('create: 403 without update on the owning record', function () {
    $actor = registryCardActor([]);
    $card = registryCard();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Email->value,
        'value' => 'grace@example.com',
    ])->assertForbidden();
});

it('create: 422 for an invalid value of the given type', function () {
    $actor = registryCardActor(['update']);
    $card = registryCard();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Email->value,
        'value' => 'not-an-email',
    ])->assertStatus(422)->assertJsonValidationErrors('value');
});

it('create: 422 when the owner is missing', function () {
    $actor = registryCardActor(['update']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'type' => ContactTypeEnum::Email->value,
        'value' => 'grace@example.com',
    ])->assertStatus(422)->assertJsonValidationErrors('contactable_type');
});

it('create: enforces a single primary per owner+type', function () {
    $actor = registryCardActor(['update']);
    $card = registryCard();
    $first = Contact::factory()->email()->primary()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Email->value,
        'value' => 'second@example.com',
        'is_primary' => true,
    ])->assertCreated();

    // The previously-primary email was demoted: exactly one primary email left.
    expect($card->contacts()->where('type', ContactTypeEnum::Email->value)->where('is_primary', true)->count())
        ->toBe(1)
        ->and($first->fresh()->is_primary)->toBeFalse();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/contacts/{contact}
// ---------------------------------------------------------------------------

it('update: 200 replaces the contact value', function () {
    $actor = registryCardActor(['update']);
    $contact = Contact::factory()->email()->for(registryCard(), 'contactable')->create(['value' => 'old@example.com']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/contacts/{$contact->id}", [
        'type' => ContactTypeEnum::Email->value,
        'value' => 'new@example.com',
    ])->assertOk()->assertJsonPath('data.value', 'new@example.com');

    $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'value' => 'new@example.com']);
});

it('update: 403 without update on the owning record', function () {
    $actor = registryCardActor([]);
    $contact = Contact::factory()->email()->for(registryCard(), 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/contacts/{$contact->id}", [
        'type' => ContactTypeEnum::Email->value,
        'value' => 'new@example.com',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/contacts/{contact}
// ---------------------------------------------------------------------------

it('delete: 204 and removes the contact', function () {
    $actor = registryCardActor(['update']);
    $contact = Contact::factory()->for(registryCard(), 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contacts/{$contact->id}")->assertNoContent();

    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
});

it('delete: 403 without update on the owning record', function () {
    $actor = registryCardActor([]);
    $contact = Contact::factory()->for(registryCard(), 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contacts/{$contact->id}")->assertForbidden();
});
