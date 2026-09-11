<?php

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * GET /leads/{id} projects the anagrafica's PRIMARY contacts inside
 * `data.registry.primary_contacts`, so the record card can call/mail the
 * contact without a second request. The contacts are the ANAGRAFICA's data:
 * they travel only when the actor may view registries, so `leads.view` alone
 * never widens into contact data.
 */
function leadContactsActor(bool $mayViewRegistries): User
{
    foreach (['leads.view', 'registries.view'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $user = User::factory()->create();
    $user->givePermissionTo('leads.view');

    if ($mayViewRegistries) {
        $user->givePermissionTo('registries.view');
    }

    return $user;
}

function leadWithRegistryContacts(): Lead
{
    $registry = Registry::factory()->create(['name' => 'Ada Registry']);
    $card = PersonalData::factory()->for($registry, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create([
        'label' => 'Work',
        'value' => 'ada@example.com',
        'is_primary' => true,
    ]);
    Contact::factory()->mobile()->for($card, 'contactable')->create([
        'value' => '+39 333 1234567',
        'is_primary' => true,
    ]);
    // Non-primary: must never reach the payload.
    Contact::factory()->phone()->for($card, 'contactable')->create([
        'value' => '+39 02 000000',
        'is_primary' => false,
    ]);

    return Lead::factory()->create([
        'registry_id' => $registry->id,
        'campaign_id' => Campaign::factory()->create()->id,
    ]);
}

it('show: registry carries its primary contacts, one per type, non-primary excluded', function () {
    $lead = leadWithRegistryContacts();
    Sanctum::actingAs(leadContactsActor(mayViewRegistries: true));

    $response = $this->getJson("/api/leads/{$lead->id}")->assertOk();

    $contacts = $response->json('data.registry.primary_contacts');

    expect($response->json('data.registry.name'))->toBe('Ada Registry')
        ->and($contacts)->toHaveCount(2)
        ->and(collect($contacts)->pluck('value')->all())
        ->toEqualCanonicalizing(['ada@example.com', '+39 333 1234567'])
        ->and(collect($contacts)->firstWhere('value', 'ada@example.com'))
        ->toMatchArray(['type' => 'email', 'label' => 'Work']);
});

it('show: an actor without registries.view gets the anagrafica but no contact values', function () {
    $lead = leadWithRegistryContacts();
    Sanctum::actingAs(leadContactsActor(mayViewRegistries: false));

    $response = $this->getJson("/api/leads/{$lead->id}")->assertOk();

    expect($response->json('data.registry.name'))->toBe('Ada Registry')
        ->and($response->json('data.registry.primary_contacts'))->toBe([]);
});

it('show: an anagrafica with no personal-data card answers with an empty contact list, not null', function () {
    $lead = Lead::factory()->create([
        'registry_id' => Registry::factory()->create()->id,
        'campaign_id' => Campaign::factory()->create()->id,
    ]);
    Sanctum::actingAs(leadContactsActor(mayViewRegistries: true));

    $response = $this->getJson("/api/leads/{$lead->id}")->assertOk();

    expect($response->json('data.registry.primary_contacts'))->toBe([]);
});

/**
 * The card is eager-loaded by LeadService::DETAIL_RELATIONS: with
 * Model::preventLazyLoading() active outside production, a missing eager-load
 * would throw here rather than degrade into an N+1 in silence.
 */
it('show: resolving the contacts never lazy-loads', function () {
    $lead = leadWithRegistryContacts();
    Sanctum::actingAs(leadContactsActor(mayViewRegistries: true));

    $this->getJson("/api/leads/{$lead->id}")->assertOk();
});
