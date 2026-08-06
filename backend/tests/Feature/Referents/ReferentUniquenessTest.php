<?php

use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Phone number, codice fiscale and partita IVA unique across the shared
 * identity namespace — users, anagrafiche and referenti — with phone and mobile
 * pooled into one channel (user directive 2026-08-06, superseding the
 * per-module/per-channel scope of 2026-08-03).
 *
 * `referentUserWith()` is the ReferentCrudTest helper, guarded so either file
 * may be the one that loads it (running a single file loads only that file).
 */
const REFERENT_PHONE = '+39 333 1234567';
const REFERENT_TAX_CODE = 'RSSMRA80A01H501U';

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

/** A referent owning one contact of the given channel/value. */
function referentWithContact(string $type, string $value): Referent
{
    $referent = Referent::factory()->create();
    cardWithContact($referent, $type, $value);

    return $referent;
}

/** A card owned by $owner, carrying one contact of the given channel/value. */
function cardWithContact(Model $owner, string $type, string $value): PersonalData
{
    $card = PersonalData::factory()->individual()->for($owner, 'personable')->create();
    Contact::factory()->for($card, 'contactable')->create(['type' => $type, 'value' => $value]);

    return $card;
}

/**
 * @param  array<int, array<string, mixed>>  $contacts
 * @param  array<string, mixed>  $identity
 * @return array<string, mixed>
 */
function referentPayloadWith(array $contacts, array $identity = []): array
{
    return [
        'contact_scope' => 'internal',
        'personal_data' => array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => $contacts,
        ], $identity),
    ];
}

// ---------------------------------------------------------------------------
// create — phone/mobile
// ---------------------------------------------------------------------------

it('create: 422 when the phone number already belongs to another referent', function () {
    $actor = referentUserWith(['create']);
    referentWithContact('phone', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([['type' => 'phone', 'value' => REFERENT_PHONE]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');

    expect(Referent::count())->toBe(1);
});

it('create: the collision ignores formatting, not just the exact string', function () {
    $actor = referentUserWith(['create']);
    // Stored as a migrated/factory row would be: never canonicalized by InputFormat.
    referentWithContact('phone', '+39 333 1234567');
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([['type' => 'phone', 'value' => '+39-333/123.45.67']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');
});

it('create: 422 when the same number sits on another referent MOBILE (channels are pooled)', function () {
    $actor = referentUserWith(['create']);
    referentWithContact('mobile', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([['type' => 'phone', 'value' => REFERENT_PHONE]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');
});

it('create: 422 when the same number belongs to an ANAGRAFICA', function () {
    $actor = referentUserWith(['create']);
    cardWithContact(Registry::factory()->create(), 'phone', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([['type' => 'phone', 'value' => REFERENT_PHONE]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');
});

it('create: 422 when the same number belongs to a USER account', function () {
    $actor = referentUserWith(['create']);
    cardWithContact(User::factory()->create(), 'mobile', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([['type' => 'phone', 'value' => REFERENT_PHONE]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');
});

it('create: 201 when the number belongs to a COMPANY SITE (outside the namespace)', function () {
    $actor = referentUserWith(['create']);
    cardWithContact(CompanySite::factory()->create(), 'phone', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([['type' => 'phone', 'value' => REFERENT_PHONE]]))
        ->assertCreated();
});

it('create: 422 when the same number is repeated twice on the submitted card', function () {
    $actor = referentUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith([
        ['type' => 'phone', 'value' => REFERENT_PHONE],
        ['type' => 'phone', 'value' => '+39 333 123 4567'],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.1.value');
});

// ---------------------------------------------------------------------------
// create — fiscal identifiers
// ---------------------------------------------------------------------------

it('create: 422 when the tax code already belongs to another referent', function () {
    $actor = referentUserWith(['create']);
    $existing = Referent::factory()->create();
    PersonalData::factory()->individual()->for($existing, 'personable')->create(['tax_code' => REFERENT_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith(
        [['type' => 'phone', 'value' => REFERENT_PHONE]],
        ['first_name' => 'Mario', 'last_name' => 'Rossi', 'tax_code' => REFERENT_TAX_CODE],
    ))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');
});

it('create: 422 when the tax code belongs to an ANAGRAFICA', function () {
    $actor = referentUserWith(['create']);
    $registry = Registry::factory()->create();
    PersonalData::factory()->individual()->for($registry, 'personable')->create(['tax_code' => REFERENT_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith(
        [['type' => 'phone', 'value' => REFERENT_PHONE]],
        ['first_name' => 'Mario', 'last_name' => 'Rossi', 'tax_code' => REFERENT_TAX_CODE],
    ))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');
});

it('create: 422 when the tax code belongs to a USER account', function () {
    $actor = referentUserWith(['create']);
    $holder = User::factory()->create();
    PersonalData::factory()->individual()->for($holder, 'personable')->create(['tax_code' => REFERENT_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', referentPayloadWith(
        [['type' => 'phone', 'value' => REFERENT_PHONE]],
        ['first_name' => 'Mario', 'last_name' => 'Rossi', 'tax_code' => REFERENT_TAX_CODE],
    ))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

it('update: 200 when the referent keeps its own number (no self-collision)', function () {
    $actor = referentUserWith(['update']);
    $target = referentWithContact('phone', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [['type' => 'phone', 'value' => REFERENT_PHONE]],
        ],
    ])->assertOk();
});

it('update: 422 when taking another referent number', function () {
    $actor = referentUserWith(['update']);
    referentWithContact('phone', REFERENT_PHONE);
    $target = referentWithContact('phone', '+39 055 9999999');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [['type' => 'phone', 'value' => REFERENT_PHONE]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts.0.value');
});

it('update: the edited record is excluded by (owner type, id), never by id alone', function () {
    $actor = referentUserWith(['update']);
    // Same primary key on two different morphs: the registry holding the number
    // must stay visible to the check while the referent under edit is excluded.
    $target = referentWithContact('phone', '+39 055 9999999');
    $registry = Registry::factory()->create();
    expect($registry->id)->toBe($target->id);
    cardWithContact($registry, 'phone', REFERENT_PHONE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [['type' => 'phone', 'value' => REFERENT_PHONE]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.contacts.0.value');
});
