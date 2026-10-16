<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Reuse the userWithUserAbilities() helper defined in UserCrudTest.php. It is a
// global function (function_exists guard), so this file relies on the suite
// loading it; redefine defensively if that file is run in isolation.
if (! function_exists('userWithUserAbilities')) {
    require_once __DIR__.'/UserCrudTest.php';
}

/**
 * Build a full, valid nested personal_data payload (individual + 1 contact +
 * 1 address). Override pieces per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fullProfilePayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'tax_code' => 'LVLDAA90A01H501X',
        'birth_date' => '1990-01-01',
        'contacts' => [
            ['type' => 'email', 'value' => 'ada@example.com', 'label' => 'Work', 'is_primary' => true],
        ],
        'addresses' => [
            ['line1' => '10 Analytical St', 'postal_code' => '00100', 'city_id' => City::factory()->create()->id, 'is_primary' => true],
        ],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userAccountPayload(array $overrides = []): array
{
    // `name` is intentionally absent: it is derived from the nested personal_data
    // card (ADR 0012), never client-supplied.
    return array_merge([
        'email' => 'new.person@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// 1. create with full personal_data
// ---------------------------------------------------------------------------

it('create: 201 persists card + contact + address and returns the tree', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', userAccountPayload([
        'employment' => ['covers_all_product_categories' => true],
        'personal_data' => fullProfilePayload(),
    ]))->assertCreated()
        ->assertJsonPath('data.personal_data.type', 'individual')
        ->assertJsonPath('data.personal_data.first_name', 'Ada')
        ->assertJsonPath('data.personal_data.contacts.0.value', 'ada@example.com')
        ->assertJsonPath('data.personal_data.addresses.0.line1', '10 Analytical St');

    $user = User::where('email', 'new.person@example.com')->firstOrFail();
    $card = $user->personalData()->firstOrFail();

    $this->assertDatabaseHas('personal_data', [
        'id' => $card->id,
        'personable_type' => 'user',
        'personable_id' => $user->id,
        'first_name' => 'Ada',
    ]);
    expect($card->contacts()->count())->toBe(1)
        ->and($card->addresses()->count())->toBe(1);

    // Address forced primary (first of the owner).
    expect($card->addresses()->first()->is_primary)->toBeTrue();
});

// ---------------------------------------------------------------------------
// 2. create without personal_data is rejected (it is the source of users.name)
// ---------------------------------------------------------------------------

it('create: 422 without personal_data (required as the name source)', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data');

    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('create: derives users.name from the submitted card', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'employment' => ['covers_all_product_categories' => true],
        'personal_data' => fullProfilePayload(['first_name' => 'Ada', 'last_name' => 'Lovelace']),
    ]))->assertCreated()
        ->assertJsonPath('data.name', 'Ada Lovelace');

    $this->assertDatabaseHas('users', ['email' => 'new.person@example.com', 'name' => 'Ada Lovelace']);
});

// ---------------------------------------------------------------------------
// 3. invalid nested data → 422 + rollback (no user)
// ---------------------------------------------------------------------------

it('create: 422 on bad contact value for its type, rolls back the user', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => fullProfilePayload([
            'contacts' => [['type' => 'email', 'value' => 'not-an-email']],
        ]),
    ]))->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');

    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('create: 422 on non-existent city_id, rolls back the user', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => fullProfilePayload([
            'addresses' => [['line1' => 'Somewhere', 'city_id' => 999999]],
        ]),
    ]))->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.addresses.0.city_id');

    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('create: 422 when an address is submitted without city_id (product decision: geo-located on create)', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => fullProfilePayload([
            'addresses' => [['line1' => 'Somewhere']],
        ]),
    ]))->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.addresses.0.city_id');

    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('update: a legacy address without city_id still succeeds (city_id stays optional on update)', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    $legacy = Address::factory()->for($card, 'addressable')->create(['city_id' => null]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'addresses' => [['id' => $legacy->id, 'line1' => 'Still no city']],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('addresses', ['id' => $legacy->id, 'line1' => 'Still no city', 'city_id' => null]);
});

it('create: 422 when an individual is missing its required name', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => [
            'type' => 'individual',
            // first_name / last_name missing
        ],
    ]))->assertStatus(422)
        ->assertJsonValidationErrors(['personal_data.first_name', 'personal_data.last_name']);

    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('create: 422 when personal_data is present without a type', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => ['first_name' => 'X'],
    ]))->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.type');
});

it('create: company requires company_name', function () {
    $actor = userWithUserAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => ['type' => 'company'],
    ]))->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.company_name');
});

// ---------------------------------------------------------------------------
// 4. update: add / update / delete children by diff
// ---------------------------------------------------------------------------

it('update: upserts the card and adds children when none existed', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => fullProfilePayload(),
    ])->assertOk()
        ->assertJsonPath('data.personal_data.contacts.0.value', 'ada@example.com');

    $card = $target->personalData()->firstOrFail();
    expect($card->contacts()->count())->toBe(1)
        ->and($card->addresses()->count())->toBe(1);
});

it('update: keeps an existing child by id and deletes the omitted one', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    $keep = Contact::factory()->email()->for($card, 'contactable')->create();
    $drop = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['id' => $keep->id, 'type' => 'email', 'value' => 'updated@example.com'],
            ],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('contacts', ['id' => $keep->id, 'value' => 'updated@example.com']);
    $this->assertDatabaseMissing('contacts', ['id' => $drop->id]);
});

it('update: absent collection key leaves that collection untouched', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    $contact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    // Submit only addresses; contacts key absent → contacts untouched.
    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'addresses' => [['line1' => 'New St']],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    expect($card->addresses()->count())->toBe(1);
});

it('update: empty array deletes all owned children', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create();
    Contact::factory()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [],
        ],
    ])->assertOk();

    expect($card->contacts()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// 5. cross-card id is treated as create, never mutates another card's child
// ---------------------------------------------------------------------------

it('update: a cross-card contact id is treated as create, not an update of the other card', function () {
    $actor = userWithUserAbilities(['update']);

    $other = User::factory()->create();
    $otherCard = PersonalData::factory()->for($other, 'personable')->create();
    $foreign = Contact::factory()->email()->for($otherCard, 'contactable')->create(['value' => 'foreign@example.com']);

    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['id' => $foreign->id, 'type' => 'email', 'value' => 'mine@example.com'],
            ],
        ],
    ])->assertOk();

    // The foreign contact is untouched...
    $this->assertDatabaseHas('contacts', ['id' => $foreign->id, 'value' => 'foreign@example.com']);
    // ...and a brand-new contact was created on the target's card.
    $targetCard = $target->personalData()->firstOrFail();
    expect($targetCard->contacts()->where('value', 'mine@example.com')->exists())->toBeTrue()
        ->and($foreign->fresh()->contactable_id)->toBe($otherCard->id);
});

// ---------------------------------------------------------------------------
// 6. single-primary invariants after a batch
// ---------------------------------------------------------------------------

it('update: keeps one primary contact per type after a batch', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['type' => 'email', 'value' => 'a@example.com', 'is_primary' => true],
                ['type' => 'email', 'value' => 'b@example.com', 'is_primary' => true],
            ],
            'addresses' => [
                ['line1' => 'A', 'is_primary' => true],
                ['line1' => 'B', 'is_primary' => true],
            ],
        ],
    ])->assertOk();

    $card = $target->personalData()->firstOrFail();

    expect($card->contacts()->where('type', 'email')->where('is_primary', true)->count())->toBe(1)
        ->and($card->addresses()->where('is_primary', true)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 7. authorization
// ---------------------------------------------------------------------------

it('create: 403 without users.create even with a profile', function () {
    $actor = userWithUserAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'personal_data' => fullProfilePayload(),
    ]))->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
});

it('update: 403 without users.update even with a profile', function () {
    $actor = userWithUserAbilities([]);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => fullProfilePayload(),
    ])->assertForbidden();

    expect($target->personalData()->exists())->toBeFalse();
});

it('create: accepts a valid geo reference (city_id) and persists it', function () {
    $actor = userWithUserAbilities(['create']);
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', userAccountPayload([
        'employment' => ['covers_all_product_categories' => true],
        'personal_data' => fullProfilePayload([
            'addresses' => [['line1' => 'Geo St', 'city_id' => $city->id]],
        ]),
    ]))->assertCreated()
        ->assertJsonPath('data.personal_data.addresses.0.city_id', $city->id);

    $this->assertDatabaseHas('addresses', ['city_id' => $city->id, 'line1' => 'Geo St']);
});
