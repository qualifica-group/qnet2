<?php

use App\Enums\ContactTypeEnum;
use App\Models\Address;
use App\Models\AgentFiscalProfile;
use App\Models\City;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function supplierUrl(CommissionSettlement $settlement, string $suffix = '/supplier'): string
{
    return "/api/commission-settlements/{$settlement->id}{$suffix}";
}

/** A referent with a complete company card, one contact and one address. */
function agentWithCard(array $card = []): Referent
{
    $referent = Referent::factory()->create();
    $personal = PersonalData::factory()->company()->for($referent, 'personable')->create(['company_name' => 'Agenzia Rossi Srl', 'vat_number' => '01234567890', 'tax_code' => null, ...$card]);
    $personal->contacts()->create(['type' => ContactTypeEnum::Email->value, 'value' => 'agente@example.com', 'is_primary' => true]);
    $personal->addresses()->create(['line1' => 'Via Roma 1', 'city_id' => City::factory()->create()->id, 'is_primary' => true]);
    AgentFiscalProfile::factory()->create(['referent_id' => $referent->id]);

    return $referent;
}

function receivedFor(Referent $referent): CommissionSettlement
{
    return settlementRow($referent, Company::factory()->create(), 'document_received');
}

it('AC-004: the draft returns the linked supplier and the defaults of the profile', function () {
    $settlement = approvableSettlement();
    $profile = $settlement->agentFiscalProfile;
    $profile->update(['payment_method_id' => PaymentMethod::factory()->create()->id, 'cost_type_id' => CostType::factory()->create()->id, 'vat_rate_id' => VatRate::factory()->create()->id]);
    Sanctum::actingAs(approvalUser());

    $this->getJson(supplierUrl($settlement, '/approval-draft'))
        ->assertOk()
        ->assertJsonPath('data.supplier.id', (int) $settlement->referent->supplier_registry_id)
        ->assertJsonPath('data.supplier_candidates', [])
        ->assertJsonPath('data.can_create_supplier', true)
        ->assertJsonPath('data.payment_method_id', $profile->payment_method_id)
        ->assertJsonPath('data.cost_type_id', $profile->cost_type_id)
        ->assertJsonPath('data.vat_rate_id', $profile->vat_rate_id)
        ->assertJsonPath('data.line_description', __('Commissions :from-:to', ['from' => '01/09/2026', 'to' => '30/09/2026']));
});

it('AC-004: without a supplier the draft lists the registries with the same tax code or VAT number', function () {
    $referent = agentWithCard();
    $match = Registry::factory()->withPersonalData(fn ($card) => $card->company()->state(['vat_number' => '01234567890', 'tax_code' => null]))->create();
    Registry::factory()->withPersonalData(fn ($card) => $card->company()->state(['vat_number' => '99999999999', 'tax_code' => null]))->create();
    $settlement = receivedFor($referent);
    Sanctum::actingAs(approvalUser(['registries.create']));

    $response = $this->getJson(supplierUrl($settlement, '/approval-draft'))->assertOk()
        ->assertJsonPath('data.supplier', null)
        ->assertJsonPath('data.can_create_supplier', false);

    expect(array_column($response->json('data.supplier_candidates'), 'id'))->toBe([$match->id])
        ->and($response->json('data.supplier_candidates.0'))->toMatchArray(['vat_number' => '01234567890', 'is_supplier' => false]);
});

it('AC-004: the draft is a 409 outside DOCUMENT_RECEIVED and a 403 without approve', function () {
    Sanctum::actingAs(approvalUser());
    $this->getJson(supplierUrl(amountedSettlement('issued'), '/approval-draft'))->assertStatus(409);

    Sanctum::actingAs(approvalUser(['commission-settlements.approve']));
    $this->getJson(supplierUrl(approvableSettlement(), '/approval-draft'))->assertForbidden();
});

it('AC-005: links an existing supplier registry to the agent', function () {
    $settlement = amountedSettlement('document_received');
    $registry = Registry::factory()->supplier()->create();
    Sanctum::actingAs(approvalUser(['registries.create']));

    $this->postJson(supplierUrl($settlement), ['registry_id' => $registry->id])
        ->assertOk()->assertJsonPath('data.supplier.id', $registry->id)->assertJsonPath('data.supplier.name', $registry->name);

    expect((int) $settlement->referent->fresh()->supplier_registry_id)->toBe($registry->id);
});

it('AC-005: a registry that is not a supplier is a 422 on registry_id', function () {
    $settlement = amountedSettlement('document_received');
    Sanctum::actingAs(approvalUser());

    $this->postJson(supplierUrl($settlement), ['registry_id' => Registry::factory()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('registry_id');

    expect($settlement->referent->fresh()->supplier_registry_id)->toBeNull();
});

it('AC-005: a null registry_id creates a supplier registry copying card, contacts and addresses of the agent', function () {
    $referent = agentWithCard();
    $settlement = receivedFor($referent);
    Sanctum::actingAs(approvalUser());

    $id = $this->postJson(supplierUrl($settlement), ['registry_id' => null])->assertOk()->json('data.supplier.id');

    $registry = Registry::query()->with('personalData.contacts', 'personalData.addresses')->findOrFail($id);

    expect($registry->is_supplier)->toBeTrue()
        ->and($registry->name)->toBe('Agenzia Rossi Srl')
        ->and($registry->personalData->vat_number)->toBe('01234567890')
        ->and($registry->personalData->contacts->pluck('value')->all())->toBe(['agente@example.com'])
        ->and($registry->personalData->addresses->pluck('line1')->all())->toBe(['Via Roma 1'])
        ->and((int) $referent->fresh()->supplier_registry_id)->toBe($id);
});

it('AC-005: creating the registry needs registries.create (403)', function () {
    $settlement = receivedFor(agentWithCard());
    Sanctum::actingAs(approvalUser(['registries.create']));

    $this->postJson(supplierUrl($settlement), ['registry_id' => null])->assertForbidden();

    expect(Registry::query()->count())->toBe(0);
});

it('AC-005: an incomplete card is a 422 listing the missing fields and writes nothing', function () {
    $referent = agentWithCard(['company_name' => null, 'vat_number' => null, 'tax_code' => null]);
    $settlement = receivedFor($referent);
    Sanctum::actingAs(approvalUser());

    $response = $this->postJson(supplierUrl($settlement), ['registry_id' => null])->assertUnprocessable()->assertJsonValidationErrors('registry_id');

    expect($response->json('errors.registry_id.0'))->toContain('company_name')->toContain('tax_code')
        ->and(Registry::query()->count())->toBe(0)
        ->and($referent->fresh()->supplier_registry_id)->toBeNull();
});

it('AC-005: an agent with approved settlements cannot move to another supplier', function () {
    $settlement = approvableSettlement();
    CommissionSettlement::factory()->approved()->create(['referent_id' => $settlement->referent_id, 'agent_fiscal_profile_id' => $settlement->agent_fiscal_profile_id]);
    $current = (int) $settlement->referent->supplier_registry_id;
    Sanctum::actingAs(approvalUser());

    $this->postJson(supplierUrl($settlement), ['registry_id' => Registry::factory()->supplier()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('registry_id');
    $this->postJson(supplierUrl($settlement), ['registry_id' => $current])->assertOk();

    expect((int) $settlement->referent->fresh()->supplier_registry_id)->toBe($current);
});

it('AC-005: the supplier link is a 409 outside DOCUMENT_RECEIVED', function () {
    Sanctum::actingAs(approvalUser());

    $this->postJson(supplierUrl(amountedSettlement('issued')), ['registry_id' => Registry::factory()->supplier()->create()->id])->assertStatus(409);
});
