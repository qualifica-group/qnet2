<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * An actor holding the given `users.*` abilities via a dedicated role that
 * additionally restricts $field to editable:false (spec 0008 write-path
 * enforcement fixtures).
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('actorWithLockedField')) {
    function actorWithLockedField(array $abilities, string $field): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $role = Role::create(['name' => 'locked-'.str_replace('.', '-', $field).'-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "users.{$ability}", $abilities));
        $role->fieldPermissions()->create([
            'resource' => 'users',
            'field' => $field,
            'visible' => true,
            'editable' => false,
            'required' => false,
        ]);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

/**
 * A minimal, valid `personal_data` payload (individual, no contacts/addresses
 * key) — used as the base for the enforcement tests, which only care about
 * the specific field/section under test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lockedFieldPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// AC-006 — CHANGE-based enforcement (scalar): personal_data.tax_code locked
// (editable:false). A DIFFERENT submitted value is rejected (422, no write);
// the IDENTICAL current value is a no-op (200).
//
// NOTE (spec 0008 mandatory bypass): retargeted from `personal_data.first_name`
// (now mandatory, so a DB row on it is bypassed and could never 422) onto the
// non-mandatory `personal_data.tax_code`.
// ---------------------------------------------------------------------------

it('AC-006: locked personal_data.tax_code — a DIFFERENT submitted value is rejected (422), no write', function () {
    $actor = actorWithLockedField(['view', 'update'], 'personal_data.tax_code');
    $target = User::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['tax_code' => 'LVLDAA80A01H501V']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload(['tax_code' => 'LVLDAA85A01H501A']),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.tax_code');

    $this->assertDatabaseHas('personal_data', ['personable_id' => $target->id, 'tax_code' => 'LVLDAA80A01H501V']);
});

it('AC-006: locked personal_data.tax_code — resubmitting the IDENTICAL value is a no-op (200)', function () {
    $actor = actorWithLockedField(['view', 'update'], 'personal_data.tax_code');
    $target = User::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['tax_code' => 'LVLDAA80A01H501V']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload(['tax_code' => 'LVLDAA80A01H501V']),
    ])->assertOk();

    $this->assertDatabaseHas('personal_data', ['personable_id' => $target->id, 'tax_code' => 'LVLDAA80A01H501V']);
});

it('AC-006: locked personal_data.birth_date (date cast) — a DIFFERENT date is rejected, the IDENTICAL date is a no-op', function () {
    $actor = actorWithLockedField(['view', 'update'], 'personal_data.birth_date');
    $target = User::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['birth_date' => '1990-01-01']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload(['birth_date' => '1991-02-02']),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.birth_date');

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload(['birth_date' => '1990-01-01']),
    ])->assertOk();

    expect($target->personalData()->firstOrFail()->birth_date->format('Y-m-d'))->toBe('1990-01-01');
});

// ---------------------------------------------------------------------------
// AC-007 — CHANGE-based enforcement (section): personal_data.contacts locked
// (editable:false). A modified contacts set is rejected (422, no write); the
// SAME set (order-insensitive, by type/value/label/is_primary) is a no-op.
// ---------------------------------------------------------------------------

it('AC-007: locked personal_data.contacts — a modified contacts set is rejected (422), no write', function () {
    $actor = actorWithLockedField(['view', 'update'], 'personal_data.contacts');
    $target = User::factory()->create();
    $card = PersonalData::factory()->individual()->for($target, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com', 'label' => 'Work', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload([
            'contacts' => [
                ['type' => 'email', 'value' => 'changed@example.com', 'label' => 'Work', 'is_primary' => true],
            ],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts');

    $this->assertDatabaseHas('contacts', ['contactable_id' => $card->id, 'value' => 'ada@example.com']);
});

it('AC-007: locked personal_data.contacts — resubmitting the IDENTICAL set (different order) is a no-op (200)', function () {
    $actor = actorWithLockedField(['view', 'update'], 'personal_data.contacts');
    $target = User::factory()->create();
    $card = PersonalData::factory()->individual()->for($target, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com', 'label' => 'Work', 'is_primary' => true]);
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 333 0000000', 'label' => null, 'is_primary' => false]);
    Sanctum::actingAs($actor);

    // Same two contacts, submitted in the REVERSE order — order-insensitive.
    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload([
            'contacts' => [
                ['type' => 'phone', 'value' => '+39 333 0000000', 'label' => null, 'is_primary' => false],
                ['type' => 'email', 'value' => 'ada@example.com', 'label' => 'Work', 'is_primary' => true],
            ],
        ]),
    ])->assertOk();

    expect($card->contacts()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-006 (create context) — with no persisted user yet, ANY non-empty value
// submitted for a locked field counts as a change (spec 0008: "no current
// value" on create).
//
// NOTE (spec 0008 mandatory bypass): retargeted from `personal_data.first_name`
// (now mandatory, so a DB row on it is bypassed and could never 422) onto the
// non-mandatory `personal_data.tax_code`.
// ---------------------------------------------------------------------------

it('AC-006 (create): locked personal_data.tax_code — a non-empty submitted value is rejected (422), no user created', function () {
    $actor = actorWithLockedField(['view', 'create'], 'personal_data.tax_code');
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'new.person@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => lockedFieldPayload(['tax_code' => 'LVLDAA80A01H501V']),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.tax_code');

    expect(User::where('email', 'new.person@example.com')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Composition (spec 0008 constraint) — the field-level 422 never precedes the
// base 403 when the actor lacks users.update entirely.
// ---------------------------------------------------------------------------

it('a 403 (no base users.update) takes precedence over the personal_data 422', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['first_name' => 'Ada']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => lockedFieldPayload(['first_name' => 'Changed']),
    ])->assertForbidden();
});
