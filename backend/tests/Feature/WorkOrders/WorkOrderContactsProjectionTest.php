<?php

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Task 428287: the commessa detail shows the client's Referente (the offer's
 * own, else the anagrafica's primary one) and the primary contacts of both,
 * so the operator can call the client. Contact values travel only to an actor
 * who may view that registry / referent.
 */
uses(RefreshDatabase::class);

function workOrderContactsCard(Model $owner, string $phone): void
{
    $card = PersonalData::factory()->for($owner, 'personable')->create();
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => $phone, 'is_primary' => true]);
    // Non-primary: must never reach the payload.
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 02 000000', 'is_primary' => false]);
}

function workOrderContactsActor(bool $mayViewContacts): User
{
    $user = workOrderUserWith(['view']);

    foreach (['registries.view', 'referents.view'] as $permission) {
        Permission::findOrCreate($permission);

        if ($mayViewContacts) {
            $user->givePermissionTo($permission);
        }
    }

    return $user;
}

/** A commessa whose offer names no referent, on a registry with a primary one. */
function workOrderOnRegistryWithPrimaryReferent(?Referent $offerReferent = null): WorkOrder
{
    $registry = Registry::factory()->create();
    $primary = Referent::factory()->create(['name' => 'Christian Taeggi']);
    $registry->referents()->attach($primary->id);
    $registry->forceFill(['primary_referent_id' => $primary->id])->save();
    workOrderContactsCard($registry, '+39 0832 111111');
    workOrderContactsCard($primary, '3391838293');

    $opportunity = Opportunity::factory()->create([
        'registry_id' => $registry->id,
        'referent_id' => $offerReferent?->id,
    ]);

    return WorkOrder::factory()->create([
        'quote_id' => Quote::factory()->create(['opportunity_id' => $opportunity->id])->id,
    ]);
}

it('falls back to the anagrafica primary referent, with its primary phone, when the offer names none', function () {
    $workOrder = workOrderOnRegistryWithPrimaryReferent();
    Sanctum::actingAs(workOrderContactsActor(mayViewContacts: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.referent.name', 'Christian Taeggi')
        ->assertJsonPath('data.referent.is_registry_primary', true)
        ->assertJsonCount(1, 'data.referent.primary_contacts')
        ->assertJsonPath('data.referent.primary_contacts.0.type', 'phone')
        ->assertJsonPath('data.referent.primary_contacts.0.value', '3391838293')
        ->assertJsonCount(1, 'data.registry.primary_contacts')
        ->assertJsonPath('data.registry.primary_contacts.0.value', '+39 0832 111111');
});

it('keeps the offer own referent when it has one', function () {
    $offerReferent = Referent::factory()->create(['name' => 'Offer Referent']);
    $workOrder = workOrderOnRegistryWithPrimaryReferent($offerReferent);
    Sanctum::actingAs(workOrderContactsActor(mayViewContacts: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.referent.id', $offerReferent->id)
        ->assertJsonPath('data.referent.is_registry_primary', false)
        ->assertJsonPath('data.referent.primary_contacts', []);
});

it('shows the referent but no contact values to an actor who may not view registries and referents', function () {
    $workOrder = workOrderOnRegistryWithPrimaryReferent();
    Sanctum::actingAs(workOrderContactsActor(mayViewContacts: false));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.referent.name', 'Christian Taeggi')
        ->assertJsonPath('data.referent.primary_contacts', [])
        ->assertJsonPath('data.registry.primary_contacts', []);
});

it('projects a null referent when neither the offer nor the anagrafica has one', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    Sanctum::actingAs(workOrderContactsActor(mayViewContacts: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.referent', null)
        ->assertJsonPath('data.registry.primary_contacts', []);
});
