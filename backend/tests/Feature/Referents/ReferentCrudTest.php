<?php

use App\Models\City;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
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
if (! function_exists('minimalReferentProfilePayload')) {
    function minimalReferentProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/referents/{referent} (AC-011)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape (including referent_type and personal_data)', function () {
    $actor = referentUserWith(['view']);
    $type = ReferentType::factory()->create(['name' => 'Commercial']);
    $target = Referent::factory()->create(['referent_type_id' => $type->id, 'contact_scope' => 'external', 'notes' => 'note']);
    PersonalData::factory()->for($target, 'personable')->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referents/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.referent_type_id', $type->id)
        ->assertJsonPath('data.referent_type.id', $type->id)
        ->assertJsonPath('data.referent_type.name', 'Commercial')
        ->assertJsonPath('data.contact_scope', 'external')
        ->assertJsonPath('data.notes', 'note')
        ->assertJsonPath('data.personal_data.first_name', 'Ada');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
    expect($response->json('permissions.fields'))->toHaveKey('personal_data.first_name');
});

it('show: referent_type is null when no type is assigned', function () {
    $actor = referentUserWith(['view']);
    $target = Referent::factory()->create(['referent_type_id' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/referents/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.referent_type_id', null)
        ->assertJsonPath('data.referent_type', null);
});

it('show: 403 without referents.view', function () {
    $actor = referentUserWith([]);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/referents/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent referent', function () {
    $actor = referentUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referents/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/referents (AC-009/AC-010)
// ---------------------------------------------------------------------------

it('create: 201 persists card and derives name from the card', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'phone', 'value' => '+39 333 1234567', 'is_primary' => true]],
        ]),
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.contact_scope', 'internal')
        ->assertJsonPath('data.personal_data.first_name', 'Ada');

    $referent = Referent::where('name', 'Ada Lovelace')->firstOrFail();
    $this->assertDatabaseHas('personal_data', [
        'personable_type' => 'referent',
        'personable_id' => $referent->id,
        'first_name' => 'Ada',
    ]);
    expect($response->json('data.id'))->toBe($referent->id);
});

it('create: with a referent type + contacts/addresses persists the whole tree', function () {
    $actor = referentUserWith(['create']);
    $type = ReferentType::factory()->create();
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'referent_type_id' => $type->id,
        'contact_scope' => 'external',
        'notes' => 'Some notes',
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [
                ['type' => 'email', 'value' => 'ada@example.com', 'is_primary' => true],
                ['type' => 'phone', 'value' => '+39 333 1234567', 'is_primary' => true],
            ],
            'addresses' => [['line1' => '10 Analytical St', 'city_id' => $city->id, 'is_primary' => true]],
        ]),
    ])->assertCreated()
        ->assertJsonPath('data.referent_type.id', $type->id)
        ->assertJsonPath('data.notes', 'Some notes')
        ->assertJsonPath('data.personal_data.contacts.0.value', 'ada@example.com')
        ->assertJsonPath('data.personal_data.addresses.0.line1', '10 Analytical St');
});

it('create: nested personal_data.addresses.*.site_type persists on the address (spec 0020, AC-003)', function () {
    $actor = referentUserWith(['create']);
    $type = ReferentType::factory()->create();
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'referent_type_id' => $type->id,
        'contact_scope' => 'external',
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'phone', 'value' => '+39 333 1234567', 'is_primary' => true]],
            'addresses' => [['line1' => '10 Analytical St', 'city_id' => $city->id, 'is_primary' => true, 'site_type' => 'legal_seat']],
        ]),
    ])->assertCreated()
        ->assertJsonPath('data.personal_data.addresses.0.site_type', 'legal_seat');
});

it('create: 422 when nested personal_data.addresses.*.site_type is outside the enum (spec 0020, AC-003)', function () {
    $actor = referentUserWith(['create']);
    $type = ReferentType::factory()->create();
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'referent_type_id' => $type->id,
        'contact_scope' => 'external',
        'personal_data' => minimalReferentProfilePayload([
            'addresses' => [['line1' => '10 Analytical St', 'city_id' => $city->id, 'site_type' => 'not-a-site-type']],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.addresses.0.site_type');
});

it('create: 422 when a nested address is missing city_id (product decision: geo-located on create)', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload([
            'addresses' => [['line1' => '10 Analytical St']],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.addresses.0.city_id');

    expect(Referent::count())->toBe(0);
});

it('create: 422 without personal_data (required as the name source)', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', ['contact_scope' => 'internal'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data');

    expect(Referent::count())->toBe(0);
});

it('create: 422 when contact_scope is missing', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', ['personal_data' => minimalReferentProfilePayload()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('contact_scope');
});

it('create: 422 when contact_scope is not a valid enum value', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'bogus',
        'personal_data' => minimalReferentProfilePayload(),
    ])->assertStatus(422)->assertJsonValidationErrors('contact_scope');
});

it('create: 422 when referent_type_id does not exist', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'referent_type_id' => 999999,
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload(),
    ])->assertStatus(422)->assertJsonValidationErrors('referent_type_id');
});

it('create: 403 without referents.create', function () {
    $actor = referentUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload(),
    ])->assertForbidden();

    expect(Referent::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// create — at least one phone number (user directive 2026-07-31)
// ---------------------------------------------------------------------------

it('create: 422 without any contact at all', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload(),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts');

    expect(Referent::count())->toBe(0);
});

it('create: 422 when the only contact is not a phone number', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'email', 'value' => 'ada@example.com', 'is_primary' => true]],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts');

    expect(Referent::count())->toBe(0);
});

it('create: 201 when the only number is a mobile (mobile counts as a phone number)', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload([
            'contacts' => [['type' => 'mobile', 'value' => '+39 333 1234567', 'is_primary' => true]],
        ]),
    ])->assertCreated();

    expect(Referent::count())->toBe(1);
});

it('create: the missing-phone 422 never masks the 403 of an actor who may not create', function () {
    $actor = referentUserWith([]);
    Sanctum::actingAs($actor);

    // No phone in the payload: the phone rule must stand aside so the Policy's
    // 403 stays the reported failure, never a 422 that leaks payload feedback
    // to someone who may not create at all.
    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => minimalReferentProfilePayload(),
    ])->assertForbidden();

    expect(Referent::count())->toBe(0);
});

it('update: does not require a phone number (the rule gates creation only)', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create(['contact_scope' => 'internal']);
    PersonalData::factory()->for($target, 'personable')->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/referents/{$target->id}", [
        'contact_scope' => 'external',
        'personal_data' => minimalReferentProfilePayload(),
    ])->assertOk();
});
