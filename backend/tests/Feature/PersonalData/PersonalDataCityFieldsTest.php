<?php

use App\Models\City;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Actors for the two write surfaces exercised here. Deliberately NOT named
 * `userWith*Abilities`: those helpers are declared (guarded) by several other
 * test files with differing ability sets, and adding one more declaration would
 * decide by load order which one wins.
 *
 * @param  array<int, string>  $abilities
 */
function cityFieldActor(string $resource, array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("{$resource}.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("{$resource}.{$ability}");
    }

    return $user;
}

// ---------------------------------------------------------------------------
// Place of birth (`birth_city_id`): a reference to the geo catalogue, not free
// text. Individual-only in the UI, nullable everywhere, and always emitted
// alongside the comune name when the relation is loaded.
// ---------------------------------------------------------------------------

it('store: persists the comune of birth and reads it back with its name', function () {
    $actor = cityFieldActor('personal_data', ['create', 'view']);
    $owner = User::factory()->create();
    $city = geoChain()['city'];
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'birth_date' => '1980-01-01',
        'birth_city_id' => $city->id,
    ])->assertCreated();

    $card = PersonalData::findOrFail($response->json('data.id'));
    expect($card->birth_city_id)->toBe($city->id)
        ->and($card->birthCity->name)->toBe('Milano');

    $this->getJson("/api/personal-data/{$card->id}")
        ->assertOk()
        ->assertJsonPath('data.birth_city_id', $city->id)
        ->assertJsonPath('data.birth_city.name', 'Milano');
});

it('store: 422 when the comune does not exist', function () {
    $actor = cityFieldActor('personal_data', ['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'birth_city_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('birth_city_id');
});

it('update: clears the comune of birth when the payload omits it', function () {
    $actor = cityFieldActor('personal_data', ['update', 'view']);
    $city = geoChain()['city'];
    $card = PersonalData::factory()->create(['birth_city_id' => $city->id]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/personal-data/{$card->id}", [
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertOk()->assertJsonPath('data.birth_city_id', null);

    expect($card->refresh()->birth_city_id)->toBeNull();
});

it('nested user write: stores the comune of birth on the card', function () {
    $actor = cityFieldActor('users', ['create']);
    $city = geoChain()['city'];
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', [
        'email' => 'ada@example.com',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'locale' => 'it',
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'birth_city_id' => $city->id,
        ],
    ])->assertCreated();

    $user = User::findOrFail($response->json('data.id'));
    expect($user->personalData->birth_city_id)->toBe($city->id);
});

// ---------------------------------------------------------------------------
// Comune of residence (`residence_city_id`): the twin of the place of birth —
// same geo reference, same nullability, same individual-only surface. The two
// are independent: writing one must never disturb the other.
// ---------------------------------------------------------------------------

it('store: persists the comune of residence and reads it back with its name', function () {
    $actor = cityFieldActor('personal_data', ['create', 'view']);
    $owner = User::factory()->create();
    $city = geoChain()['city'];
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'residence_city_id' => $city->id,
    ])->assertCreated();

    $card = PersonalData::findOrFail($response->json('data.id'));
    expect($card->residence_city_id)->toBe($city->id)
        ->and($card->residenceCity->name)->toBe('Milano');

    $this->getJson("/api/personal-data/{$card->id}")
        ->assertOk()
        ->assertJsonPath('data.residence_city_id', $city->id)
        ->assertJsonPath('data.residence_city.name', 'Milano');
});

it('store: 422 when the comune of residence does not exist', function () {
    $actor = cityFieldActor('personal_data', ['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'residence_city_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('residence_city_id');
});

it('update: clears the comune of residence when the payload omits it', function () {
    $actor = cityFieldActor('personal_data', ['update', 'view']);
    $city = geoChain()['city'];
    $card = PersonalData::factory()->create(['residence_city_id' => $city->id]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/personal-data/{$card->id}", [
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertOk()->assertJsonPath('data.residence_city_id', null);

    expect($card->refresh()->residence_city_id)->toBeNull();
});

it('store: keeps birth and residence independent when both are given', function () {
    $actor = cityFieldActor('personal_data', ['create', 'view']);
    $owner = User::factory()->create();
    $chain = geoChain();
    $birthCity = $chain['city'];
    $residenceCity = City::factory()->create([
        'name' => 'Torino',
        'province_id' => $chain['province']->id,
        'state_id' => $chain['state']->id,
        'country_id' => $chain['country']->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'birth_city_id' => $birthCity->id,
        'residence_city_id' => $residenceCity->id,
    ])->assertCreated();

    $card = PersonalData::findOrFail($response->json('data.id'));
    expect($card->birth_city_id)->toBe($birthCity->id)
        ->and($card->residence_city_id)->toBe($residenceCity->id);
});

it('nested user write: stores the comune of residence on the card', function () {
    $actor = cityFieldActor('users', ['create']);
    $city = geoChain()['city'];
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', [
        'email' => 'grace@example.com',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'locale' => 'it',
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'residence_city_id' => $city->id,
        ],
    ])->assertCreated();

    $user = User::findOrFail($response->json('data.id'));
    expect($user->personalData->residence_city_id)->toBe($city->id);
});
