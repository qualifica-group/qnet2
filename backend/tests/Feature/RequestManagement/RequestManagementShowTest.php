<?php

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/request-management/{quote} (spec 0049 data_contract, AC-020/021/022;
// migrated onto the Quote by spec 0086, D-2).

uses(RefreshDatabase::class);

if (! function_exists('requestManagementUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll', 'transferContact'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteWithContacts')) {
    /**
     * A fresh Quote linked to a Registry + Referent (through its Opportunity),
     * each carrying a PersonalData card with one contact channel (spec 0049
     * D-6). The `owner` ref exposed to the frontend must point at the
     * PersonalData card itself (`registryCard`/`referentCard`), not the
     * entity, since `contactable_type` only accepts `personal_data`.
     *
     * @return array{quote: Quote, opportunity: Opportunity, registry: Registry, referent: Referent, registryCard: PersonalData, referentCard: PersonalData}
     */
    function quoteWithContacts(): array
    {
        $registry = Registry::factory()->create();
        $registryCard = PersonalData::factory()->for($registry, 'personable')->create();
        Contact::factory()->email()->for($registryCard, 'contactable')->create([
            'value' => 'client@example.com',
            'is_primary' => true,
        ]);

        $referent = Referent::factory()->create();
        $referentCard = PersonalData::factory()->for($referent, 'personable')->create();
        Contact::factory()->mobile()->for($referentCard, 'contactable')->create([
            'value' => '+39 333 0000000',
            'is_primary' => true,
        ]);

        $opportunity = Opportunity::factory()->create([
            'registry_id' => $registry->id,
            'referent_id' => $referent->id,
        ]);

        $quote = Quote::factory()->for($opportunity)->create();

        return [
            'quote' => $quote,
            'opportunity' => $opportunity,
            'registry' => $registry,
            'referent' => $referent,
            'registryCard' => $registryCard,
            'referentCard' => $referentCard,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-020 — full contract shape as the offer's Supervisore
// ---------------------------------------------------------------------------

it('GET as the offer supervisor returns the full work-panel shape (AC-020)', function () {
    $actor = requestManagementUserWith(['view']);
    [
        'quote' => $quote,
        'opportunity' => $opportunity,
        'registry' => $registry,
        'referent' => $referent,
        'registryCard' => $registryCard,
        'referentCard' => $referentCard,
    ] = quoteWithContacts();
    $quote->update(['supervisor_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/request-management/{$quote->id}")->assertOk();

    $response->assertJsonPath('data.id', $quote->id)
        ->assertJsonPath('data.opportunity_id', $opportunity->id)
        ->assertJsonPath('data.name', $opportunity->name)
        ->assertJsonPath('data.registry', ['id' => $registry->id, 'name' => $registry->name])
        ->assertJsonPath('data.referent', ['id' => $referent->id, 'name' => $referent->name])
        ->assertJsonPath('data.commercial', null)
        // Spec 0086, D-1: a request-management row is always backed by a
        // Quote — the panel's own subject — so the status is always resolved
        // from THAT quote's own workflow status, never the "no quote yet"
        // fallback (which no longer applies to this module).
        ->assertJsonPath('data.status.source', 'quotes')
        ->assertJsonPath('data.status.distinct_count', 1)
        ->assertJsonPath('data.status.entries.0.count', 1)
        ->assertJsonPath('data.client_contacts.owner', ['type' => 'personal_data', 'id' => $registryCard->id])
        ->assertJsonPath('data.client_contacts.items.0.value', 'client@example.com')
        ->assertJsonPath('data.client_contacts.items.0.is_primary', true)
        ->assertJsonPath('data.referent_contacts.owner', ['type' => 'personal_data', 'id' => $referentCard->id])
        ->assertJsonPath('data.referent_contacts.items.0.value', '+39 333 0000000')
        ->assertJsonPath('data.context', [
            'estimated_value' => $opportunity->estimated_value,
            'expected_close_date' => $opportunity->expected_close_date?->format('Y-m-d'),
            'success_probability' => $opportunity->success_probability,
            // User directive 2026-07-27: read-only in this module.
            'general_notes' => $opportunity->general_notes,
        ])
        ->assertJsonStructure([
            'data' => ['status', 'product_lines'],
            'permissions' => ['resource', 'fields', 'actions'],
        ]);

    expect($response->json('data'))->not->toHaveKey('workflow_status')
        ->and($response->json('data'))->not->toHaveKey('workflow_statuses');
    expect($response->json('permissions.resource.view'))->toBeTrue();
});

it('client_contacts/referent_contacts owner is null when the entity has no PersonalData card (D-6)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create([
        'registry_id' => Registry::factory()->create()->id,
        'referent_id' => Referent::factory()->create()->id,
    ]);
    $quote = Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.client_contacts.owner', null)
        ->assertJsonPath('data.client_contacts.items', [])
        ->assertJsonPath('data.referent_contacts.owner', null)
        ->assertJsonPath('data.referent_contacts.items', []);
});

// ---------------------------------------------------------------------------
// AC-021 — scope guard + view permission gate
// ---------------------------------------------------------------------------

it('GET on a quote the actor does not supervise and without viewAll -> 403 (AC-021)', function () {
    $actor = requestManagementUserWith(['view']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")->assertForbidden();
});

it('GET on an unsupervised quote with viewAll -> 200 (AC-021)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")->assertOk();
});

it('GET without request-management.view -> 403 even for a supervised quote (AC-021)', function () {
    $actor = requestManagementUserWith([]);
    $quote = Quote::factory()->create(['supervisor_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")->assertForbidden();
});

// Spec 0084, D-1: the former AC-022 (`applicable_attributes` union dedup-per-
// code across product lines' categories) is GONE — the dynamic attribute set
// is now resolved from the Offerta's OWN offer lines, see
// tests/Feature/Quotes/QuoteAttributeValuesTest.php.

// ---------------------------------------------------------------------------
// User directive 2026-07-27 — the opportunity's "Note generali" surface in the
// work panel's read-only context block (the FE highlights them at the top of
// the side column). This module never writes the field.
// ---------------------------------------------------------------------------

it('context.general_notes carries the opportunity notes, read-only', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create([
        'registry_id' => Registry::factory()->create()->id,
        'general_notes' => 'Il cliente richiama a settembre.',
    ]);
    $quote = Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.context.general_notes', 'Il cliente richiama a settembre.');
});

it('a PATCH attempting to write general_notes from this module leaves the field untouched', function () {
    $actor = requestManagementUserWith(['view', 'viewAll', 'update']);
    $opportunity = Opportunity::factory()->create([
        'registry_id' => Registry::factory()->create()->id,
        'general_notes' => 'Nota originale',
    ]);
    $quote = Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['general_notes' => 'Riscritta']);

    expect($opportunity->fresh()->general_notes)->toBe('Nota originale');
});

// ---------------------------------------------------------------------------
// Spec 0079 — permissions.actions.transfer_contact gates the panel's
// "Trasferisci contatto" button.
// ---------------------------------------------------------------------------

it('permissions.actions.transfer_contact is true with both request-management.update and .transferContact', function () {
    // Explicit findOrCreate: `requestManagementUserWith` is a
    // function_exists-guarded helper shared by several test files in this
    // directory, so whichever file's copy loads first wins for the whole
    // run and may predate this ability — don't depend on it for existence.
    Permission::findOrCreate('request-management.transferContact');
    $actor = requestManagementUserWith(['view', 'viewAll', 'update', 'transferContact']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.transfer_contact', true);
});

it('permissions.actions.transfer_contact is false without request-management.transferContact', function () {
    Permission::findOrCreate('request-management.transferContact');
    $actor = requestManagementUserWith(['view', 'viewAll', 'update']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.transfer_contact', false);
});

it('permissions.actions.transfer_contact is false with .transferContact but without request-management.update', function () {
    // The double gate mirrors RequestManagementController::transfer(),
    // which requires update AND transferContact — this exposed the wrong
    // predicate ("true" while the endpoint would 403) until fixed.
    Permission::findOrCreate('request-management.transferContact');
    $actor = requestManagementUserWith(['view', 'viewAll', 'transferContact']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.transfer_contact', false);
});
