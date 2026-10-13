<?php

use App\Imports\Leads\LeadDuplicateMatcher;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-10-08: a company's codice fiscale is usually its partita
// IVA, so a fiscal identifier is matched against BOTH columns — the blocking
// write gate (UniquePersonalDataIdentifier) and the live duplicate panel
// (IdentityDuplicateFinder) alike, and the lead import's duplicate match
// (LeadDuplicateMatcher). Column-to-column matching let the same
// company in twice: once with only its CF, once with only its P.IVA.

uses(RefreshDatabase::class);

const CROSS_MATCH_NUMBER = '09876543217';

function crossMatchActor(): User
{
    foreach (['viewAny', 'view', 'create'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['registries.viewAny', 'registries.view', 'registries.create']);

    return $actor;
}

/** @return array<string, mixed> */
function crossMatchCompanyPayload(array $identity): array
{
    return [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(array_merge([
            'type' => 'company',
            'first_name' => null,
            'last_name' => null,
            'company_name' => 'Nuova Srl',
        ], $identity)),
    ];
}

function anagraficaHolding(array $fiscal): Registry
{
    $registry = Registry::factory()->create();
    PersonalData::factory()->company()->for($registry, 'personable')
        ->create(array_merge(['tax_code' => null, 'vat_number' => null], $fiscal));

    return $registry;
}

it('write gate: refuses a codice fiscale another card stores as partita IVA', function () {
    anagraficaHolding(['vat_number' => CROSS_MATCH_NUMBER]);
    Sanctum::actingAs(crossMatchActor());

    $this->postJson('/api/registries', crossMatchCompanyPayload(['tax_code' => CROSS_MATCH_NUMBER]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['personal_data.tax_code' => 'already assigned']);
});

it('write gate: refuses a partita IVA another card stores as codice fiscale', function () {
    anagraficaHolding(['tax_code' => CROSS_MATCH_NUMBER]);
    Sanctum::actingAs(crossMatchActor());

    $this->postJson('/api/registries', crossMatchCompanyPayload(['vat_number' => CROSS_MATCH_NUMBER]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['personal_data.vat_number' => 'already assigned']);
});

it('write gate: a card carrying the same number as both CF and P.IVA is a valid create', function () {
    Sanctum::actingAs(crossMatchActor());

    $this->postJson('/api/registries', crossMatchCompanyPayload([
        'tax_code' => CROSS_MATCH_NUMBER,
        'vat_number' => CROSS_MATCH_NUMBER,
    ]))->assertCreated();
});

it('duplicate panel: reports a codice fiscale held elsewhere as partita IVA', function () {
    $holder = anagraficaHolding(['vat_number' => CROSS_MATCH_NUMBER]);
    Sanctum::actingAs(crossMatchActor());

    $response = $this->postJson('/api/identity/duplicate-check', ['tax_code' => CROSS_MATCH_NUMBER])->assertOk();

    expect($response->json('data.matches'))->toHaveCount(1)
        ->and($response->json('data.matches.0'))->toMatchArray([
            'owner_type' => 'registry',
            'owner_id' => $holder->id,
            'matched_on' => ['tax_code'],
        ]);
});

it('lead import: a row tax code matches the anagrafica holding it as partita IVA', function () {
    $holder = anagraficaHolding(['vat_number' => CROSS_MATCH_NUMBER]);

    $match = app(LeadDuplicateMatcher::class)->match(['tax_code' => CROSS_MATCH_NUMBER]);

    expect($match?->registryId)->toBe($holder->id)
        ->and($match?->matchedOn)->toBe(['tax_code']);
});
