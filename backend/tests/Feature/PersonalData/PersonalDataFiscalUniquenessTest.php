<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\CompanySite;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-10-08: the generic POST/PUT /api/personal-data endpoint
// applies the same fiscal uniqueness as the owner forms
// (UniquePersonalDataIdentifier, cross-column) when the card belongs to the
// identity namespace. A company-site card is never checked; a card keeping its
// own values never collides with itself.

uses(RefreshDatabase::class);

const CARD_ENDPOINT_VAT = '09876543217';

const CARD_ENDPOINT_TAX_CODE = 'LVLDAA80A01H501V';

function cardEndpointActor(): User
{
    foreach (['create', 'update'] as $ability) {
        Permission::findOrCreate("personal_data.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['personal_data.create', 'personal_data.update']);

    return $actor;
}

/** @return array<string, mixed> */
function cardEndpointCompany(array $overrides = []): array
{
    return array_merge([
        'type' => PersonalDataTypeEnum::Company->value,
        'company_name' => 'Acme Srl',
    ], $overrides);
}

it('create: 422 when the tax code already belongs to an anagrafica', function () {
    PersonalData::factory()->individual()->for(Registry::factory()->create(), 'personable')
        ->create(['tax_code' => CARD_ENDPOINT_TAX_CODE]);
    $owner = User::factory()->create();
    Sanctum::actingAs(cardEndpointActor());

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'tax_code' => CARD_ENDPOINT_TAX_CODE,
    ])->assertStatus(422)->assertJsonValidationErrors(['tax_code' => 'already assigned']);
});

it('update: 422 when the VAT number is held elsewhere as a tax code (cross-column)', function () {
    $card = PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['tax_code' => null, 'vat_number' => null]);
    PersonalData::factory()->company()->for(Referent::factory()->create(), 'personable')
        ->create(['tax_code' => CARD_ENDPOINT_VAT, 'vat_number' => null]);
    Sanctum::actingAs(cardEndpointActor());

    $this->putJson("/api/personal-data/{$card->id}", cardEndpointCompany(['vat_number' => CARD_ENDPOINT_VAT]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['vat_number' => 'already assigned']);
});

it('update: a card keeping its own VAT number is not a collision', function () {
    $card = PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['tax_code' => null, 'vat_number' => CARD_ENDPOINT_VAT]);
    Sanctum::actingAs(cardEndpointActor());

    $this->putJson("/api/personal-data/{$card->id}", cardEndpointCompany([
        'company_name' => 'Acme Rinominata Srl',
        'vat_number' => CARD_ENDPOINT_VAT,
    ]))->assertOk();
});

it('update: a company-site card is outside the namespace and never checked', function () {
    $siteCard = PersonalData::factory()->company()->for(CompanySite::factory()->create(), 'personable')
        ->create(['tax_code' => null, 'vat_number' => null]);
    PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['vat_number' => CARD_ENDPOINT_VAT]);
    Sanctum::actingAs(cardEndpointActor());

    $this->putJson("/api/personal-data/{$siteCard->id}", cardEndpointCompany(['vat_number' => CARD_ENDPOINT_VAT]))
        ->assertOk();
});
