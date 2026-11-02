<?php

use App\Models\Address;
use App\Models\Contact;
use App\Models\OperationalSite;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
 * Spec 0328: addresses and contacts follow the record that owns their card,
 * its policy and its `personal_data.{section}` field-matrix row.
 */

uses(RefreshDatabase::class);

it('AC-001: update on the owning user record writes its addresses and contacts', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('users.view'), Permission::findOrCreate('users.update'));
    $card = PersonalData::factory()->for(User::factory()->create(), 'personable')->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Via Roma 1',
    ])->assertCreated();

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'email',
        'value' => 'ada@example.com',
    ])->assertCreated();
});

it('AC-002: view only on the owning record reads but does not write', function () {
    $actor = registryCardActor(['view']);
    $card = registryCard();
    $address = Address::factory()->for($card, 'addressable')->create();
    $contact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/addresses/{$address->id}")->assertOk();
    $this->getJson("/api/contacts/{$contact->id}")->assertOk();
    $this->putJson("/api/addresses/{$address->id}", ['line1' => 'New Street'])->assertForbidden();
    $this->deleteJson("/api/contacts/{$contact->id}")->assertForbidden();
    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Via Roma 1',
    ])->assertForbidden();
});

it('AC-003: a hidden section row denies reading it', function () {
    $actor = registryCardActor(['view', 'update'], [
        'personal_data.addresses' => ['visible' => false, 'editable' => false],
    ]);
    $card = registryCard();
    $address = Address::factory()->for($card, 'addressable')->create();
    $contact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/addresses/{$address->id}")->assertForbidden();
    $this->getJson("/api/contacts/{$contact->id}")->assertOk();
});

it('AC-003: a read-only section row denies writing it', function () {
    $actor = registryCardActor(['view', 'update'], [
        'personal_data.contacts' => ['visible' => true, 'editable' => false],
    ]);
    $card = registryCard();
    $address = Address::factory()->for($card, 'addressable')->create();
    $contact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/contacts/{$contact->id}")->assertOk();
    $this->putJson("/api/contacts/{$contact->id}", ['type' => 'email', 'value' => 'new@example.com'])->assertForbidden();
    $this->putJson("/api/addresses/{$address->id}", ['line1' => 'New Street'])->assertOk();
});

it('AC-004: an address not owned by a card is never authorized', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.view'), Permission::findOrCreate('operational-sites.update'));
    $address = Address::factory()->for(OperationalSite::factory()->create(), 'addressable')->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/addresses/{$address->id}")->assertForbidden();
    $this->deleteJson("/api/addresses/{$address->id}")->assertForbidden();
});

it('AC-004: a card without an owner is never authorized', function () {
    $actor = registryCardActor(['view', 'update']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Via Roma 1',
    ])->assertForbidden();
});

it('AC-005: the address and contact permissions are pruned and never synced again', function () {
    Permission::findOrCreate('addresses.update');
    Permission::findOrCreate('contacts.view');

    (require database_path('migrations/2026_11_09_100000_prune_address_and_contact_permissions.php'))->up();
    Artisan::call('permissions:sync');

    expect(Permission::query()->where('name', 'like', 'addresses.%')->orWhere('name', 'like', 'contacts.%')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'registries.update')->exists())->toBeTrue();
});
