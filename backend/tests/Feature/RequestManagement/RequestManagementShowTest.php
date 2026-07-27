<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/request-management/{opportunity} (spec 0049 data_contract, AC-020/021/022).

uses(RefreshDatabase::class);

if (! function_exists('requestManagementUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('opportunityWithContacts')) {
    /**
     * A fresh Opportunity linked to a Registry + Referent, each carrying a
     * PersonalData card with one contact channel (spec 0049 D-6). The
     * `owner` ref exposed to the frontend must point at the PersonalData
     * card itself (`registryCard`/`referentCard`), not the entity, since
     * `contactable_type` only accepts `personal_data`.
     *
     * @return array{opportunity: Opportunity, registry: Registry, referent: Referent, registryCard: PersonalData, referentCard: PersonalData}
     */
    function opportunityWithContacts(): array
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

        return [
            'opportunity' => $opportunity,
            'registry' => $registry,
            'referent' => $referent,
            'registryCard' => $registryCard,
            'referentCard' => $referentCard,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-020 — full contract shape as the opportunity's Account Manager
// ---------------------------------------------------------------------------

it('GET as the opportunity manager returns the full work-panel shape (AC-020)', function () {
    $actor = requestManagementUserWith(['view']);
    [
        'opportunity' => $opportunity,
        'registry' => $registry,
        'referent' => $referent,
        'registryCard' => $registryCard,
        'referentCard' => $referentCard,
    ] = opportunityWithContacts();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/request-management/{$opportunity->id}")->assertOk();

    $response->assertJsonPath('data.id', $opportunity->id)
        ->assertJsonPath('data.name', $opportunity->name)
        ->assertJsonPath('data.registry', ['id' => $registry->id, 'name' => $registry->name])
        ->assertJsonPath('data.referent', ['id' => $referent->id, 'name' => $referent->name])
        ->assertJsonPath('data.commercial', null)
        ->assertJsonPath('data.opportunity_status.id', $opportunity->opportunity_status_id)
        ->assertJsonPath('data.client_contacts.owner', ['type' => 'personal_data', 'id' => $registryCard->id])
        ->assertJsonPath('data.client_contacts.items.0.value', 'client@example.com')
        ->assertJsonPath('data.client_contacts.items.0.is_primary', true)
        ->assertJsonPath('data.referent_contacts.owner', ['type' => 'personal_data', 'id' => $referentCard->id])
        ->assertJsonPath('data.referent_contacts.items.0.value', '+39 333 0000000')
        ->assertJsonPath('data.applicable_attributes', [])
        ->assertJsonPath('data.attribute_values', [])
        ->assertJsonPath('data.context', [
            'estimated_value' => $opportunity->estimated_value,
            'expected_close_date' => $opportunity->expected_close_date?->format('Y-m-d'),
            'success_probability' => $opportunity->success_probability,
            // User directive 2026-07-27: read-only in this module.
            'general_notes' => $opportunity->general_notes,
        ])
        ->assertJsonStructure([
            'data' => ['workflow_status', 'workflow_statuses', 'product_lines'],
            'permissions' => ['resource', 'fields', 'actions'],
        ]);

    expect($response->json('data.workflow_statuses'))->not->toBeEmpty();
    expect($response->json('permissions.resource.view'))->toBeTrue();
});

it('client_contacts/referent_contacts owner is null when the entity has no PersonalData card (D-6)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create([
        'registry_id' => Registry::factory()->create()->id,
        'referent_id' => Referent::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.client_contacts.owner', null)
        ->assertJsonPath('data.client_contacts.items', [])
        ->assertJsonPath('data.referent_contacts.owner', null)
        ->assertJsonPath('data.referent_contacts.items', []);
});

// ---------------------------------------------------------------------------
// AC-021 — scope guard + view permission gate
// ---------------------------------------------------------------------------

it('GET on an opportunity the actor does not manage and without viewAll -> 403 (AC-021)', function () {
    $actor = requestManagementUserWith(['view']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")->assertForbidden();
});

it('GET on an unmanaged opportunity with viewAll -> 200 (AC-021)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")->assertOk();
});

it('GET without request-management.view -> 403 even for a managed opportunity (AC-021)', function () {
    $actor = requestManagementUserWith([]);
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-022 — applicable_attributes union dedup-per-code + empty cases
// ---------------------------------------------------------------------------

it('applicable_attributes is the union dedup-per-code across all product lines\' categories (AC-022)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $businessFunction = BusinessFunction::factory()->create();
    $categoryOne = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $categoryTwo = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $shared = Attribute::factory()->create(['code' => 'shared_code']);
    $onlyOne = Attribute::factory()->create(['code' => 'only_one_code']);
    $categoryOne->attributes()->attach($shared->id, ['is_required' => false, 'sort_order' => 0]);
    $categoryOne->attributes()->attach($onlyOne->id, ['is_required' => false, 'sort_order' => 1]);
    $categoryTwo->attributes()->attach($shared->id, ['is_required' => true, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $categoryOne->id,
    ]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $categoryTwo->id,
    ]);
    Sanctum::actingAs($actor);

    $applicable = $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->json('data.applicable_attributes');

    expect($applicable)->toHaveCount(2);
    $byCode = collect($applicable)->keyBy('code');
    // strictest requirement wins across the merged categories.
    expect($byCode['shared_code']['is_required'])->toBeTrue();
    expect($byCode['only_one_code']['is_required'])->toBeFalse();
});

it('applicable_attributes is empty for an opportunity with no product lines (AC-022)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.applicable_attributes', []);
});

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
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.context.general_notes', 'Il cliente richiama a settembre.');
});

it('a PATCH attempting to write general_notes from this module leaves the field untouched', function () {
    $actor = requestManagementUserWith(['view', 'viewAll', 'update']);
    $opportunity = Opportunity::factory()->create([
        'registry_id' => Registry::factory()->create()->id,
        'general_notes' => 'Nota originale',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['general_notes' => 'Riscritta']);

    expect($opportunity->fresh()->general_notes)->toBe('Nota originale');
});
