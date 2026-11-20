<?php

use App\Enums\SiteTypeEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0400: the commessa detail carries the legacy "Info cliente" block of
 * its client — legal seat with fiscal ids, tags, email/PEC, phones,
 * Referente 1 (the principale) and 2, Commerciale, sectors and comments.
 * Personal data travel only to an actor who may view that registry/referent.
 */
uses(RefreshDatabase::class);

function clientInfoActor(bool $mayViewPersonalData): User
{
    $user = workOrderUserWith(['view']);

    foreach (['registries.view', 'referents.view'] as $permission) {
        Permission::findOrCreate($permission);

        if ($mayViewPersonalData) {
            $user->givePermissionTo($permission);
        }
    }

    return $user;
}

function clientInfoReferent(string $name, string $phone): Referent
{
    $referent = Referent::factory()->create(['name' => $name]);
    $card = PersonalData::factory()->for($referent, 'personable')->create();
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => $phone, 'is_primary' => true]);

    return $referent;
}

/** A commessa whose client has every "Info cliente" field filled. */
function clientInfoWorkOrder(): WorkOrder
{
    $registry = Registry::factory()->create(['general_notes' => 'Cliente storico']);
    $card = PersonalData::factory()->for($registry, 'personable')->create(['vat_number' => '01234567890', 'tax_code' => 'RSSMRA80A01H501U']);
    Address::factory()->for($card, 'addressable')->create(['site_type' => SiteTypeEnum::Delivery, 'line1' => 'Via Consegna 1', 'is_primary' => true]);
    Address::factory()->for($card, 'addressable')->create(['site_type' => SiteTypeEnum::LegalSeat, 'line1' => 'Via Legale 9', 'is_primary' => false]);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'info@acme.it', 'is_primary' => true]);
    Contact::factory()->pec()->for($card, 'contactable')->create(['value' => 'acme@pec.it', 'is_primary' => true]);
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 02 222222', 'is_primary' => false]);
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 02 111111', 'is_primary' => true]);

    $second = clientInfoReferent('Second Referent', '333 2');
    $first = clientInfoReferent('First Referent', '333 1');
    $third = clientInfoReferent('Third Referent', '333 3');
    $registry->referents()->attach([$second->id, $first->id, $third->id]);
    $registry->forceFill([
        'primary_referent_id' => $first->id,
        'secondary_referent_id' => $third->id,
        'commercial_id' => Referent::factory()->create(['name' => 'Agente Rossi'])->id,
    ])->save();
    $registry->sectors()->attach(Sector::factory()->create(['code' => 'EA 28', 'name' => 'Costruzioni'])->id);

    CustomFieldDefinition::factory()->forEntity('registries')->ofType('relation')->create([
        'key' => 'tags',
        'relation_target' => ['cardinality' => 'many', 'entity_type' => 'tags', 'for_select_resource' => 'tags'],
    ]);
    $tags = [Tag::factory()->create(['name' => 'VIP'])->id, Tag::factory()->create(['name' => 'Edilizia'])->id];
    CustomFieldValue::query()->create(['entity_type' => 'registries', 'entity_id' => $registry->id, 'values' => ['tags' => $tags]]);

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

    return WorkOrder::factory()->create([
        'quote_id' => Quote::factory()->create(['opportunity_id' => $opportunity->id])->id,
    ]);
}

it('projects the client info of the commessa client', function () {
    $workOrder = clientInfoWorkOrder();
    Sanctum::actingAs(clientInfoActor(mayViewPersonalData: true));

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    expect($response->json('data.client_info.legal_address'))->toStartWith('Via Legale 9');
    $response
        ->assertJsonPath('data.client_info.vat_number', '01234567890')
        ->assertJsonPath('data.client_info.tax_code', 'RSSMRA80A01H501U')
        ->assertJsonPath('data.client_info.tags', ['Edilizia', 'VIP'])
        ->assertJsonPath('data.client_info.emails.0.type', 'email')
        ->assertJsonPath('data.client_info.emails.0.value', 'info@acme.it')
        ->assertJsonPath('data.client_info.emails.1.type', 'pec')
        ->assertJsonPath('data.client_info.emails.1.value', 'acme@pec.it')
        ->assertJsonPath('data.client_info.phones.0.value', '+39 02 111111')
        ->assertJsonPath('data.client_info.phones.1.value', '+39 02 222222')
        ->assertJsonPath('data.client_info.commercial.name', 'Agente Rossi')
        ->assertJsonPath('data.client_info.sectors', ['EA 28 — Costruzioni'])
        ->assertJsonPath('data.client_info.comments', 'Cliente storico');
});

// REQUIREMENT CHANGED (spec 0408 D-5): Referente 2 is the anagrafica's secondario, not the next linked referent.
it('puts the principale as Referente 1 and the secondario as Referente 2, with their phones', function () {
    $workOrder = clientInfoWorkOrder();
    Sanctum::actingAs(clientInfoActor(mayViewPersonalData: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.client_info.referents')
        ->assertJsonPath('data.client_info.referents.0.name', 'First Referent')
        ->assertJsonPath('data.client_info.referents.0.primary_contacts.0.value', '333 1')
        ->assertJsonPath('data.client_info.referents.1.name', 'Third Referent')
        ->assertJsonPath('data.client_info.referents.1.primary_contacts.0.value', '333 3');
});

it('leaves Referente 2 empty without a secondario, even with other linked referents (spec 0408 D-5)', function () {
    $workOrder = clientInfoWorkOrder();
    $workOrder->quote->opportunity->registry->forceFill(['secondary_referent_id' => null])->save();
    Sanctum::actingAs(clientInfoActor(mayViewPersonalData: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.client_info.referents.0.name', 'First Referent')
        ->assertJsonPath('data.client_info.referents.1', null);
});

it('hides address, fiscal ids and contact values from an actor who may not view registries and referents', function () {
    $workOrder = clientInfoWorkOrder();
    Sanctum::actingAs(clientInfoActor(mayViewPersonalData: false));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.client_info.legal_address', null)
        ->assertJsonPath('data.client_info.vat_number', null)
        ->assertJsonPath('data.client_info.tax_code', null)
        ->assertJsonPath('data.client_info.emails', [])
        ->assertJsonPath('data.client_info.phones', [])
        ->assertJsonPath('data.client_info.referents.0.name', 'First Referent')
        ->assertJsonPath('data.client_info.referents.0.primary_contacts', [])
        ->assertJsonPath('data.client_info.tags', ['Edilizia', 'VIP'])
        ->assertJsonPath('data.client_info.comments', 'Cliente storico');
});

it('has no client info on a commessa without a single offer (spec 0267)', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => null]);
    Sanctum::actingAs(clientInfoActor(mayViewPersonalData: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.client_info', null);
});

it('keeps empty lists when the client has no tags, contacts or referents', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    Sanctum::actingAs(clientInfoActor(mayViewPersonalData: true));

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.client_info.tags', [])
        // REQUIREMENT CHANGED (spec 0408 D-5): the two slots are positional, both empty.
        ->assertJsonPath('data.client_info.referents', [null, null])
        ->assertJsonPath('data.client_info.comments', null);
});
