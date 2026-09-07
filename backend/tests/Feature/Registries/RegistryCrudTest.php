<?php

use App\Models\City;
use App\Models\Contact;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Source;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

/**
 * Minimal valid nested personal_data payload (individual, no children).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
if (! function_exists('minimalRegistryProfilePayload')) {
    function minimalRegistryProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            // An anagrafica must carry a phone number at creation (user
            // directive 2026-09-07): a payload without one is no longer a
            // valid create, so the minimal one holds it.
            'contacts' => [['type' => 'phone', 'value' => '+39 02 1112223', 'is_primary' => true]],
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/registries (AC-008, AC-009, AC-010)
// ---------------------------------------------------------------------------

it('create: 201 persists the card, syncs the 3 pivots and sets the 4 belongsTo FKs', function () {
    $actor = registryUserWith(['create']);
    $source = Source::factory()->create();
    $sector = Sector::factory()->create();
    $referent = Referent::factory()->create();
    $manager = User::factory()->create();
    // Supervisor is an INTERNAL user (not a referent) since the FK re-point.
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/registries', [
        'source_id' => $source->id,
        'sector_ids' => [$sector->id],
        'referent_ids' => [$referent->id],
        'manager_slots' => [$manager->id],
        'supervisor_id' => $supervisor->id,
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated();

    $response->assertJsonPath('data.source.id', $source->id)
        ->assertJsonPath('data.sector_ids', [$sector->id])
        ->assertJsonPath('data.referent_ids', [$referent->id])
        ->assertJsonPath('data.manager_ids', [$manager->id])
        ->assertJsonPath('data.supervisor.id', $supervisor->id)
        ->assertJsonPath('data.name', 'Ada Lovelace');

    $registry = Registry::first();
    expect($registry->personalData)->not->toBeNull()
        ->and($registry->personalData->personable_type)->toBe('registry')
        ->and($registry->sectors->pluck('id')->all())->toBe([$sector->id])
        ->and($registry->referents->pluck('id')->all())->toBe([$referent->id])
        ->and($registry->managers->pluck('id')->all())->toBe([$manager->id]);
});

it('create: 422 without personal_data (required as the name source)', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', ['is_supplier' => false])
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data');
});

it('create: 422 when a relational id does not exist', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'source_id' => 999999,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertStatus(422)->assertJsonValidationErrors('source_id');
});

it('create: 422 when agreement_status is outside the enum', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'agreement_status' => 'not-a-status',
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertStatus(422)->assertJsonValidationErrors('agreement_status');
});

it('create: is_supplier=false normalizes is_qualified_supplier to false (AC-010)', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'is_qualified_supplier' => true,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated()->assertJsonPath('data.is_qualified_supplier', false);

    expect(Registry::first()->is_qualified_supplier)->toBeFalse();
});

it('create: 201 with a nested address carrying line1 + city_id', function () {
    $actor = registryUserWith(['create']);
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload([
            'addresses' => [['line1' => 'Via Roma 1', 'city_id' => $city->id]],
        ]),
    ])->assertCreated()
        ->assertJsonPath('data.personal_data.addresses.0.city_id', $city->id);
});

it('show: the address tree exposes the hydrated city/province/state/country names', function () {
    $actor = registryUserWith(['create', 'view']);
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->for($country, 'country')->create(['name' => 'Campania']);
    $province = Province::factory()->for($country, 'country')->for($state, 'state')->create(['name' => 'Napoli']);
    $city = City::factory()->forProvince($province)->create(['name' => 'Napoli']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload([
            'addresses' => [[
                'line1' => 'Via del pozzo 23',
                'country_id' => $country->id,
                'state_id' => $state->id,
                'province_id' => $province->id,
                'city_id' => $city->id,
            ]],
        ]),
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/registries/{$created}")
        ->assertOk()
        ->assertJsonPath('data.personal_data.addresses.0.city.name', 'Napoli')
        ->assertJsonPath('data.personal_data.addresses.0.province.name', 'Napoli')
        ->assertJsonPath('data.personal_data.addresses.0.state.name', 'Campania')
        ->assertJsonPath('data.personal_data.addresses.0.country.name', 'Italia');
});

it('show: supervisor (user) and commercial (referent) expose their PRIMARY contacts', function () {
    $actor = registryUserWith(['view']);

    // Supervisor = internal user with a primary email.
    $supervisor = User::factory()->create();
    $supervisorCard = PersonalData::factory()->for($supervisor, 'personable')->create();
    Contact::factory()->email()->for($supervisorCard, 'contactable')->create([
        'value' => 'sup@example.com',
        'is_primary' => true,
    ]);
    Contact::factory()->email()->for($supervisorCard, 'contactable')->create([
        'value' => 'secondary@example.com',
        'is_primary' => false,
    ]);

    // Commercial = external referent with a primary phone.
    $commercial = Referent::factory()->create();
    $commercialCard = PersonalData::factory()->for($commercial, 'personable')->create();
    Contact::factory()->for($commercialCard, 'contactable')->create([
        'type' => 'phone',
        'value' => '+390812345678',
        'is_primary' => true,
    ]);

    $registry = Registry::factory()->create([
        'supervisor_id' => $supervisor->id,
        'commercial_id' => $commercial->id,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('data.supervisor.primary_contacts.0.value', 'sup@example.com')
        ->assertJsonCount(1, 'data.supervisor.primary_contacts')
        ->assertJsonPath('data.commercial.primary_contacts.0.value', '+390812345678')
        ->assertJsonPath('data.reporter', null);
});

it('create: 422 when a nested address is missing city_id (product decision: geo-located on create)', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload([
            'addresses' => [['line1' => 'Via Roma 1']],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.addresses.0.city_id');

    expect(Registry::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// create — at least one phone number (user directive 2026-09-07, the rule the
// referenti already carried since 2026-07-31)
// ---------------------------------------------------------------------------

it('create: 422 without any contact at all', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(['contacts' => []]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts');

    expect(Registry::count())->toBe(0);
});

it('create: 422 when the only contact is not a phone number', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload([
            'contacts' => [['type' => 'email', 'value' => 'ada@example.com', 'is_primary' => true]],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts');

    expect(Registry::count())->toBe(0);
});

it('create: 201 when the only number is a mobile (mobile counts as a phone number)', function () {
    $actor = registryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload([
            'contacts' => [['type' => 'mobile', 'value' => '+39 333 1234567', 'is_primary' => true]],
        ]),
    ])->assertCreated();

    expect(Registry::count())->toBe(1);
});

it('create: the missing-phone 422 never masks the 403 of an actor who may not create', function () {
    $actor = registryUserWith([]);
    Sanctum::actingAs($actor);

    // No phone in the payload: the phone rule must stand aside so the Policy's
    // 403 stays the reported failure, never a 422 that leaks payload feedback
    // to someone who may not create at all.
    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(['contacts' => []]),
    ])->assertForbidden();

    expect(Registry::count())->toBe(0);
});

it('update: does not require a phone number (the rule gates creation only)', function () {
    $actor = registryUserWith(['update']);
    $target = Registry::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/registries/{$target->id}", [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(['contacts' => []]),
    ])->assertOk();
});

it('create: 403 without registries.create', function () {
    $actor = registryUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// show — GET /api/registries/{registry} (AC-011)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape and permissions block', function () {
    $actor = registryUserWith(['view']);
    $registry = Registry::factory()->withPersonalData()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $registry->id)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'source', 'sectors', 'referents', 'managers', 'supervisor', 'commercial', 'reporter', 'personal_data'],
            'permissions' => ['resource', 'fields', 'actions'],
        ]);
});

it('show: 403 without registries.view', function () {
    $actor = registryUserWith([]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/registries/{$registry->id}")->assertForbidden();
});

it('show: 404 for a non-existent registry', function () {
    $actor = registryUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/registries/{registry} (AC-012, AC-013)
// ---------------------------------------------------------------------------

it('update: PATCH with only sector_ids=[] detaches all sectors, leaves the rest untouched', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->create(['vat_group' => 'VG-1']);
    $sector = Sector::factory()->create();
    $registry->sectors()->sync([$sector->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['sector_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.sector_ids', []);

    expect($registry->fresh()->sectors)->toHaveCount(0)
        ->and($registry->fresh()->vat_group)->toBe('VG-1');
});

it('update: PATCH omitting sector_ids leaves existing sectors untouched', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->create();
    $sector = Sector::factory()->create();
    $registry->sectors()->sync([$sector->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'Untouched-pivot'])->assertOk();

    expect($registry->fresh()->sectors->pluck('id')->all())->toBe([$sector->id]);
});

it('update: PATCH supervisor_id=null removes the supervisor', function () {
    $actor = registryUserWith(['update']);
    $supervisor = User::factory()->create();
    $registry = Registry::factory()->create(['supervisor_id' => $supervisor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['supervisor_id' => null])
        ->assertOk()
        ->assertJsonPath('data.supervisor', null);

    expect($registry->fresh()->supervisor_id)->toBeNull();
});

it('update: PATCH updates every scalar field in one request', function () {
    $actor = registryUserWith(['update']);
    $source = Source::factory()->create();
    $commercial = Referent::factory()->create();
    $reporter = Referent::factory()->create();
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'source_id' => $source->id,
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'vat_group' => 'VG-ALL',
        'is_supplier' => true,
        'is_qualified_supplier' => true,
        'agreement_status' => 'agreed',
        'agreement_notes' => 'All good',
        'size_class' => 'large',
        'employee_count' => 250,
    ])->assertOk()
        ->assertJsonPath('data.source.id', $source->id)
        ->assertJsonPath('data.commercial.id', $commercial->id)
        ->assertJsonPath('data.reporter.id', $reporter->id)
        ->assertJsonPath('data.vat_group', 'VG-ALL')
        ->assertJsonPath('data.is_supplier', true)
        ->assertJsonPath('data.is_qualified_supplier', true)
        ->assertJsonPath('data.agreement_status', 'agreed')
        ->assertJsonPath('data.agreement_notes', 'All good')
        ->assertJsonPath('data.size_class', 'large')
        ->assertJsonPath('data.employee_count', 250);
});

it('update: PATCH personal_data.addresses full-replaces the addresses', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->withPersonalData()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => minimalRegistryProfilePayload([
            'addresses' => [['line1' => 'New Address 1', 'is_primary' => true, 'site_type' => 'legal_seat']],
        ]),
    ])->assertOk()
        ->assertJsonPath('data.personal_data.addresses.0.line1', 'New Address 1')
        ->assertJsonPath('data.personal_data.addresses.0.site_type', 'legal_seat');
});

it('update: PATCH personal_data.contacts full-replaces the contacts', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->withPersonalData()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => minimalRegistryProfilePayload([
            'contacts' => [['type' => 'email', 'value' => 'new@example.com', 'is_primary' => true]],
        ]),
    ])->assertOk()->assertJsonPath('data.personal_data.contacts.0.value', 'new@example.com');
});

it('update: changing the card re-derives name', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->withPersonalData()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => minimalRegistryProfilePayload(['first_name' => 'Grace', 'last_name' => 'Hopper']),
    ])->assertOk()->assertJsonPath('data.name', 'Grace Hopper');
});

it('update: 403 without registries.update (before any field-level 422)', function () {
    $actor = registryUserWith([]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'X'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/registries/{registry} (AC-014)
// ---------------------------------------------------------------------------

it('delete: 204 cascades the personal-data card and the pivot rows', function () {
    $actor = registryUserWith(['delete']);
    $registry = Registry::factory()->withPersonalData()->create();
    $card = $registry->personalData;
    $sector = Sector::factory()->create();
    $registry->sectors()->sync([$sector->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/registries/{$registry->id}")->assertNoContent();

    $this->assertDatabaseMissing('registries', ['id' => $registry->id]);
    $this->assertDatabaseMissing('personal_data', ['id' => $card->id]);
    $this->assertDatabaseMissing('sector_registry', ['registry_id' => $registry->id]);
});

it('delete: 403 without registries.delete', function () {
    $actor = registryUserWith([]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/registries/{$registry->id}")->assertForbidden();
});
