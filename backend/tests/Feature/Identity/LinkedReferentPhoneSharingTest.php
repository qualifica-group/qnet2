<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-11-11: an anagrafica and the referenti linked to it
// (`referent_registry`) may hold the same phone number, in both directions —
// a small company's switchboard is often its owner's mobile. The number stays
// refused when the holder is NOT linked to the record being written.

uses(RefreshDatabase::class);

const LINKED_SHARED_PHONE = '3334445566';

/**
 * @param  array<int, string>  $permissions
 */
function linkedPhoneActor(array $permissions): User
{
    $actor = User::factory()->create();

    foreach ($permissions as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission));
    }

    return $actor;
}

function linkedPhoneCard(Model $owner, ?string $phone): PersonalData
{
    $card = PersonalData::factory()->individual()->for($owner, 'personable')->create();

    if ($phone !== null) {
        Contact::factory()->for($card, 'contactable')->create(['type' => 'phone', 'value' => $phone]);
    }

    return $card;
}

/**
 * @return array<string, mixed>
 */
function linkedPhoneProfile(string $phone): array
{
    return [
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'contacts' => [['type' => 'phone', 'value' => $phone, 'is_primary' => true]],
    ];
}

it('registry update: accepts the phone its own linked referente already holds', function () {
    $referent = Referent::factory()->create();
    linkedPhoneCard($referent, LINKED_SHARED_PHONE);
    $registry = Registry::factory()->create();
    $registry->referents()->sync([$referent->id]);
    linkedPhoneCard($registry, null);
    Sanctum::actingAs(linkedPhoneActor(['registries.update']));

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => linkedPhoneProfile('333 444 5566'),
    ])->assertOk();
});

it('registry update: still refuses the phone of a referente linked to ANOTHER anagrafica', function () {
    $referent = Referent::factory()->create();
    linkedPhoneCard($referent, LINKED_SHARED_PHONE);
    Registry::factory()->create()->referents()->sync([$referent->id]);
    $registry = Registry::factory()->create();
    linkedPhoneCard($registry, null);
    Sanctum::actingAs(linkedPhoneActor(['registries.update']));

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => linkedPhoneProfile(LINKED_SHARED_PHONE),
    ])->assertStatus(422)->assertJsonValidationErrors(['personal_data.contacts.0.value' => 'already assigned']);
});

it('registry create: accepts the phone of a referente submitted with it, refuses it without the link', function () {
    $referent = Referent::factory()->create();
    linkedPhoneCard($referent, LINKED_SHARED_PHONE);
    Sanctum::actingAs(linkedPhoneActor(['registries.create']));

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => linkedPhoneProfile(LINKED_SHARED_PHONE),
    ])->assertStatus(422)->assertJsonValidationErrors(['personal_data.contacts.0.value' => 'already assigned']);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'referent_ids' => [$referent->id],
        'personal_data' => linkedPhoneProfile(LINKED_SHARED_PHONE),
    ])->assertCreated();
});

it('registry update: a referente unlinked by the same payload no longer shares its number', function () {
    $referent = Referent::factory()->create();
    linkedPhoneCard($referent, LINKED_SHARED_PHONE);
    $registry = Registry::factory()->create();
    $registry->referents()->sync([$referent->id]);
    linkedPhoneCard($registry, null);
    Sanctum::actingAs(linkedPhoneActor(['registries.update']));

    $this->patchJson("/api/registries/{$registry->id}", [
        'referent_ids' => [],
        'personal_data' => linkedPhoneProfile(LINKED_SHARED_PHONE),
    ])->assertStatus(422)->assertJsonValidationErrors(['personal_data.contacts.0.value' => 'already assigned']);
});

it('referent update: accepts the phone of an anagrafica it is linked to, not of an unlinked one', function () {
    $linked = Registry::factory()->create();
    linkedPhoneCard($linked, LINKED_SHARED_PHONE);
    linkedPhoneCard(Registry::factory()->create(), '3337778899');
    $referent = Referent::factory()->create();
    $referent->registries()->sync([$linked->id]);
    linkedPhoneCard($referent, null);
    Sanctum::actingAs(linkedPhoneActor(['referents.update']));

    $this->patchJson("/api/referents/{$referent->id}", ['personal_data' => linkedPhoneProfile(LINKED_SHARED_PHONE)])
        ->assertOk();

    $this->patchJson("/api/referents/{$referent->id}", ['personal_data' => linkedPhoneProfile('3337778899')])
        ->assertStatus(422)->assertJsonValidationErrors(['personal_data.contacts.0.value' => 'already assigned']);
});

it('contacts endpoint: an anagrafica card accepts the phone of its linked referente', function () {
    $referent = Referent::factory()->create();
    linkedPhoneCard($referent, LINKED_SHARED_PHONE);
    $registry = Registry::factory()->create();
    $registry->referents()->sync([$referent->id]);
    $card = linkedPhoneCard($registry, null);
    Sanctum::actingAs(linkedPhoneActor(['registries.update']));

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'phone',
        'value' => LINKED_SHARED_PHONE,
    ])->assertCreated();
});
